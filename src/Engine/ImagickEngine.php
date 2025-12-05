<?php
/**
 * Notes:
 * User: wudg <544038230@qq.com>
 * Date: 2025/12/3 09:23
 */

namespace Wudg\PdfImages\Engine;
use Hyperf\Stringable\Str;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use ImagickException;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Wudg\PdfImages\Exception\PdfImagesException;
use Wudg\PdfImages\Interface\HandleInterface;

class ImagickEngine extends PdfImagesEngine implements HandleInterface
{

    /**
     * 统一宽度，保证转换的图片宽度一致，高度自动计算
     * @var int|mixed
     */
    protected int $width = 1191;


    /**
     * 生成图片 dpi 值，越大占用存储空间越大，越清晰，耗时越长
     * @var int|mixed
     */
    protected int $dpi = 300;

    /**
     * 生成图片扩展名称
     * @var string|mixed
     */
    protected string $ext = 'jpeg';

    /**
     * 压缩比例，0-100，越大越清晰
     * @var int|mixed
     */
    protected int $compression_quality = 100;

    protected array $config = [];

    protected ?Imagick $im = null;

    /**
     * 最大开启携程上数量
     * @var int
     */
    protected int $maxParallel = 5;


    /**
     * 生成图片保存路径
     * @var string|mixed
     */
    protected string $images_save_path = './cache/images/';


    /**
     * 生成 pdf 保存路径
     * @var string
     */
    protected string $pdf_save_path = './cache/pdf/';


    public function __construct(array $config = [])
    {
        if(empty(Imagick::queryFormats('PDF')))
        {
            throw new PdfImagesException('Imagick 未启用 PDF 读取支持（缺少 Ghostscript 或被 policy.xml 禁用）');
        }
        $this->config = $config;

        if(!empty($this->config['dpi']))
        {
            $this->dpi = $this->config['dpi'];
        }

        if(!empty($this->config['width']))
        {
            $this->width = $this->config['width'];
        }
        if(!empty($this->config['compression_quality']))
        {
            $this->compression_quality = $this->config['compression_quality'];
        }


        if(!empty($this->config['images_save_path']))
        {
            $this->images_save_path = $this->config['images_save_path'];
        }

        if(!empty($this->config['ext']))
        {
            $this->ext = $this->config['ext'];
        }

    }

    public function pdfToImages(string $pdfPath, string $savePath = null, array $options = []): array
    {
        if($savePath)
        {
            $this->images_save_path = $savePath;
        }else{
            $this->images_save_path = $this->config['save_img_path'];
        }
        $this->mkdirPath($this->images_save_path);
        $this->im = new Imagick();
        $this->im->setResolution($this->dpi, $this->dpi); //设置分辨率 值越大分辨率越高
        $this->im->setCompressionQuality($this->compression_quality);
        $pdfPageNum = $this->getPdfPages($pdfPath);

        /**
         * 判断是否在携程环境下，携程环境下 使用携程增加处理速度
         */
        if(Coroutine::inCoroutine())
        {
            $imagesPath = $this->handleParallel($pdfPageNum,$pdfPath);
        }else{ //非携程环境
            $imagesPath = $this->handleFpm($pdfPageNum,$pdfPath);
        }
        return $imagesPath;
    }


    protected function mkdirPath($savePath)
    {
        $savePath = rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if(!is_dir($savePath))  mkdir($savePath,0777,true);
    }

    /**
     * FPM 模式执行
     * @param int $pdfPageNum
     * @param string $pdfPath
     * @return array
     */
    public function handleFpm(int $pdfPageNum,string $pdfPath): array
    {
        try
        {
            $imagesPath = [];
            if($pdfPageNum == 0)
            {
                $this->im->readImage($pdfPath);
                $pdfPageNum = $this->im->getNumberImages();
            }
            for ($i = 0; $i < $pdfPageNum; $i++) {
                $imagePath = $this->toImg($this->im,$i,$pdfPath);
                if(!empty($imagePath)) $imagesPath[] = $imagePath;
            }
            return $imagesPath;
        } catch (ImagickException $e) {
            throw new PdfImagesException($e->getMessage());
        }

    }

    /**
     * 携程模式处理 pdf 转图片逻辑
     * @param int $pdfPageNum
     * @param string $pdfPath
     * @return array
     * @throws ImagickException
     */
    public function handleParallel(int $pdfPageNum,string $pdfPath): array
    {
        $parallel = new Parallel($this->maxParallel);
        if($pdfPageNum == 0)
        {
            $this->im->readImage($pdfPath);
            $pdfPageNum = $this->im->getNumberImages();
        }
        for ($i = 0; $i < $pdfPageNum; $i++) {
            $parallel->add(function () use ($pdfPath, $i) {
                $im = new Imagick();
                $imgLocalPath = $this->toImg($im,$i,$pdfPath);
                return $imgLocalPath;
            });
        }
        try{
            $processImages = $parallel->wait();
            unset($parallel);
            return $processImages;
        } catch(ParallelExecutionException $e){
            throw new PdfImagesException($e->getMessage());
        }
    }

    protected function toImg(Imagick $im, int $pageNum,string $pdfPath)
    {
        try
        {
            $imgLocalPath = null;
            $im->readImage($pdfPath."[{$pageNum}]");
            list($width,$height) = [$im->getImageWidth(),$im->getImageHeight()];
            $calcH = bcdiv(bcmul($this->width , $height),$width);
            $page = $im->getImage();
            $page->setImageFormat($this->ext);
            $page->setImageDepth(8);// 每通道 8bit，总 24-bit color
            $page->setType(Imagick::IMGTYPE_TRUECOLOR); // 标记为真彩色
            $page->setImageColorspace(Imagick::COLORSPACE_RGB);// 设置为 RGB 色彩空间
            $page->scaleImage($this->width,$calcH,true); //缩放图片
            switch ($this->ext)
            {
                case 'png':
                    $imgCompression = Imagick::INTERLACE_PNG;
                    break;
                case 'jpeg':
                default:
                    $imgCompression = Imagick::COMPRESSION_JPEG;
                    break;
            }
            $page->setImageCompression($imgCompression);
            $page->flattenImages(); //合并图层
            $page->setImageBackgroundColor('white');
            $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE); // 移除透明度
            $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN); // 合并图层
            $page->setImageCompressionQuality($this->compression_quality);
            $fileName = $pageNum.'_'.Str::uuid()->toString() . '.' . $this->ext;

            if ($page->writeImage($this->images_save_path . $fileName) == true) {
                $imgLocalPath = $this->images_save_path.$fileName;
            }
            $page->clear();
            $page->destroy();
            return $imgLocalPath;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }
    }


    /**
     * 通过命令行获取pdf页数
     * @param string $pdfPath
     * @return int|void
     */
    public function getPdfPages(string $pdfPath)
    {
        $cmd = "pdfinfo " . escapeshellarg($pdfPath) . " 2>&1";
        $output = shell_exec($cmd);
        if (!$output) return 0;

        if (preg_match('/Pages:\s+(\d+)/i', $output, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    public function toPdfImg($file)
    {
        $image = new Imagick($file);
        list($width,$height) = [$image->getImageWidth(),$image->getImageHeight()];
        $calcH = bcdiv(bcmul($this->width , $height),$width);
        $image->scaleImage($this->width, $calcH);
        $image->setResolution($this->dpi, $this->dpi); //设置分辨率 值越大分辨率越高
        $image->setCompressionQuality($this->compression_quality);
        // 调整图片大小
        $image->setImagePage($this->width, $calcH, 0,0);
        return $image;
    }

    /**
     * fpm 模式下处理 图片转 pdf
     * @param Imagick $pdf
     * @param array $images
     * @return Imagick
     * @throws PdfImagesException
     */
    public function handleFpmToPdf(Imagick $pdf,array $images)
    {
        foreach($images as $file)
        {
            try {

                if(!file_exists($file)) continue;
                $image = new Imagick($file);
                list($width,$height) = [$image->getImageWidth(),$image->getImageHeight()];
                $calcH = bcdiv(bcmul($this->width , $height),$width);
                $image->scaleImage($this->width, $calcH);
                $image->setResolution($this->dpi, $this->dpi); //设置分辨率 值越大分辨率越高
                $image->setCompressionQuality($this->compression_quality);
                // 调整图片大小
                $image->setImagePage($this->width, $calcH, 0,0);
                $pdf->addImage($image);
                $image->clear();
                $image->destroy();
            }catch (ImagickException $e)
            {
                throw new PdfImagesException($e->getMessage());
            }
        }
        return $pdf;
    }


    /**
     * 携程处理图片合成 pdf
     * @param Imagick $pdf
     * @param array $images
     * @return Imagick
     */
    public function handleParallelToPdf(Imagick $pdf,array $images)
    {

        $parallel = new Parallel($this->maxParallel);
        foreach($images as $file)
        {
            $parallel->add(function () use ($file) {
                return $this->toPdfImg($file);
            });
        }
        try{
            $processImages = $parallel->wait();
            foreach($processImages as $image)
            {
                $pdf->addImage($image);
                $image->clear();
                $image->destroy();
            }
            return $pdf;
        } catch(ParallelExecutionException $e){
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 图片合成 pdf
     * @param array $images
     * @param string|null $savePath
     * @param string|null $dirPath
     * @return string
     * @throws PdfImagesException
     */
    public function imagesToPdf(array $images,string $savePath = null): string
    {
        $imagesData = [];

        if($savePath)
        {
            $this->pdf_save_path = $savePath;
        }else{
            $this->pdf_save_path = $this->config['save_pdf_path'];
        }
        $this->mkdirPath($this->pdf_save_path);
        try {
            $pdf = new Imagick();;
            if(Coroutine::inCoroutine())
            {
                $pdf = $this->handleParallelToPdf($pdf,$images);
            }else{
                $pdf = $this->handleFpmToPdf($pdf,$images);
            }

            $pdf->readImages($imagesData);
            $savePdfPath = $this->pdf_save_path.DIRECTORY_SEPARATOR."pdf_".Str::uuid()->toString().".pdf";
            $res = $pdf->writeImages($savePdfPath, true);
            $pdf->clear();
            $pdf->destroy();
            if(!$res)
            {
                throw new PdfImagesException("生成失败");
            }
            return $savePdfPath;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }

    }

    /**
     * 打开资源
     * @param string $file
     * @return $this
     * @throws ImagickException
     */
    public function openImage(string $file)
    {
        if (!file_exists($file)) {
            throw new PdfImagesException('文件不存在');
        }
        $this->im = new Imagick($file);
        return $this;
    }

    /**
     * 添加文字
     * @param string $text
     * @param array $options
     * @return $this
     */
    public function addText(string $text, array $options = [])
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        $draw = new ImagickDraw();
        $color = $options['color'] ?? 'rgba(255,255,255,0.6)'; //60% 不透明度的白色
        $font = $options['font'] ?? null;
        $size = $options['size'] ?? 24;
        $angle = $options['angle'] ?? 0;
        $position = $options['position'] ?? 'center';
        $offsetX = $options['offset_x'] ?? 10;
        $offsetY = $options['offset_y'] ?? 10;
        $draw->setFillColor(new ImagickPixel($color));
        if ($font && file_exists($font)) {
            $draw->setFont($font);
        }
        $draw->setFontSize($size);
        $draw->setTextAntialias(true);
        $draw->setGravity($this->resolveGravity($position));
        $this->im->annotateImage($draw, $offsetX, $offsetY, $angle, $text);
        return $this;
    }


    /**
     * 图片添加文字
     * @param string $file
     * @param string $text
     * @param array $options
     * @param string|null $savePath
     * @return string
     */
    public function watermarkText(string $file,string $text, array $options = [], string $savePath = null): string
    {
        try {
            if (!file_exists($file)) {
                throw new PdfImagesException('文件不存在');
            }
            $this->im  = new Imagick($file);
            $this->addText($text, $options);
            $dir = !empty($savePath) ? $savePath : dirname($file);
            $this->mkdirPath($dir);
            $ext = pathinfo($file, PATHINFO_EXTENSION) ?: $this->ext;
            $base = pathinfo($file, PATHINFO_FILENAME);
            $newPath = $dir . $base . '_wm.' . $ext;
            $this->im->writeImage($newPath);
            $this->im->clear();
            $this->im->destroy();
            return $newPath;
        } catch (ImagickException $e) {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 返回二进制流
     * @return string
     */
    public function toBlod()
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        $blod = $this->im->getImageBlob();
        $this->im->clear();
        $this->im->destroy();
        return $blod;
    }

    /**
     * 返回路径
     * @param string|null $savePath
     * @param string $ext
     * @return string
     */
    public function toPath(string $savePath = null,string $ext = null)
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        $dir = $savePath ? rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $this->images_save_path;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $imgExt = $this->ext;
        if(!empty($ext)) $imgExt= $ext;
        $page = $this->im->getImage();
        $page->setImageFormat($imgExt);
        $newPath = $dir . 'out_'.Str::uuid()->toString().'.'.$imgExt;

        $res = $page->writeImage($newPath);
        $this->im->clear();
        $this->im->destroy();
        unset($page);
        if(!$res)
        {
            throw new PdfImagesException("生成失败");
        }
        return $newPath;
    }
    /**
     * 返回 base64
     * @return string
     */
    public function toBase64()
    {

        return base64_encode($this->toBlod());
    }

    /**
     * 图片合并
     * @param string $overlayPath
     * @param array $options
     * @return ImagickEngine
     */
    public function mergeImage(string $overlayPath, array $options = [])
    {
        if (!file_exists($overlayPath)) {
            throw new PdfImagesException('合并图片不存在');
        }
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        try {
            $overlay = new Imagick($overlayPath);
            $ow = $overlay->getImageWidth();
            $oh = $overlay->getImageHeight();
            $bw = $this->im->getImageWidth();
            $bh = $this->im->getImageHeight();

            $scaleW = $options['width'] ?? null;
            $scaleH = $options['height'] ?? null;
            $keep = $options['keep_aspect'] ?? true;
            if ($scaleW || $scaleH) {
                if ($keep) {
                    if ($scaleW && !$scaleH) {
                        $scaleH = (int)round($oh * ($scaleW / $ow));
                    } elseif ($scaleH && !$scaleW) {
                        $scaleW = (int)round($ow * ($scaleH / $oh));
                    }
                    $overlay->scaleImage($scaleW ?? $ow, $scaleH ?? $oh, true);
                } else {
                    $overlay->resizeImage($scaleW ?? $ow, $scaleH ?? $oh, Imagick::FILTER_LANCZOS, 1);
                }
                $ow = $overlay->getImageWidth();
                $oh = $overlay->getImageHeight();
            }
            $position = $options['position'] ?? 'center';
            $offsetX = (int)($options['offset_x'] ?? 0);
            $offsetY = (int)($options['offset_y'] ?? 0);
            [$x, $y] = $this->computePosition($bw, $bh, $ow, $oh, $position, $offsetX, $offsetY);
            $this->im->compositeImage($overlay, Imagick::COMPOSITE_DEFAULT, $x, $y);
            $overlay->clear();
            $overlay->destroy();
            return $this;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }

    }


    /**
     * 图片裁剪
     * @param int $width
     * @param int $height
     * @param int $x
     * @param int $y
     * @return $this
     */
    public function crop(int $width, int $height, int $x, int $y)
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        $this->im->cropImage($width, $height, $x, $y);
        $this->im->setImagePage(0, 0, 0, 0);
        return $this;
    }

    /**
     * 图片缩放
     * @param int $width
     * @param int|null $height
     * @param bool $keepAspect
     * @return ImagickEngine
     */
    public function resize(int $width, ?int $height = null, bool $keepAspect = true)
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        try {
            if ($keepAspect) {
                if ($height === null) {
                    $ow = $this->im->getImageWidth();
                    $oh = $this->im->getImageHeight();
                    $height = (int)round($oh * ($width / $ow));
                }
                $this->im->scaleImage($width, $height, true);
            } else {
                $this->im->resizeImage($width, $height ?? $this->im->getImageHeight(), Imagick::FILTER_LANCZOS, 1);
            }
            return $this;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }

    }


    /**
     * 计算图片位置
     * @param int $bw
     * @param int $bh
     * @param int $ow
     * @param int $oh
     * @param string $position
     * @param int $offsetX
     * @param int $offsetY
     * @return int[]
     */
    protected function computePosition(int $bw, int $bh, int $ow, int $oh, string $position, int $offsetX, int $offsetY): array
    {
        switch ($position) {
            case 'left_top':
                return [$offsetX, $offsetY];
            case 'top':
                return [(int)(($bw - $ow) / 2) + $offsetX, $offsetY];
            case 'right_top':
                return [$bw - $ow - $offsetX, $offsetY];
            case 'left_center':
                return [$offsetX, (int)(($bh - $oh) / 2) + $offsetY];
            case 'right_center':
                return [$bw - $ow - $offsetX, (int)(($bh - $oh) / 2) + $offsetY];
            case 'left_down':
                return [$offsetX, $bh - $oh - $offsetY];
            case 'down':
                return [(int)(($bw - $ow) / 2) + $offsetX, $bh - $oh - $offsetY];
            case 'right_down':
                return [$bw - $ow - $offsetX, $bh - $oh - $offsetY];
            case 'center':
            default:
                return [(int)(($bw - $ow) / 2) + $offsetX, (int)(($bh - $oh) / 2) + $offsetY];
        }
    }

    protected function resolveGravity(string $position): int
    {
        $map = [
            'left_top' => Imagick::GRAVITY_NORTHWEST,
            'top' => Imagick::GRAVITY_NORTH,
            'right_top' => Imagick::GRAVITY_NORTHEAST,
            'left_center' => Imagick::GRAVITY_WEST,
            'center' => Imagick::GRAVITY_CENTER,
            'right_center' => Imagick::GRAVITY_EAST,
            'left_down' => Imagick::GRAVITY_SOUTHWEST,
            'down' => Imagick::GRAVITY_SOUTH,
            'right_down' => Imagick::GRAVITY_SOUTHEAST,
        ];
        return $map[$position] ?? Imagick::GRAVITY_CENTER;
    }
}
