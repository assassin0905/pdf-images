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


        if(!empty($this->config['save_img_path']))
        {
            $this->images_save_path = $this->config['save_img_path'];
        }
        if(!empty($this->config['save_pdf_path']))
        {
            $this->pdf_save_path = $this->config['save_pdf_path'];
        }
        /**
         * 最大携程数量
         */
        if(!empty($this->config['parallel_num']))
        {
            $this->maxParallel = (int)$this->config['parallel_num'];
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
        }elseif(!empty($this->config['save_img_path'])){
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
            $page->flattenImages(); //合并图层
            $page->setImageBackgroundColor('white');
            $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE); // 移除透明度
            $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN); // 合并图层
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
            $savePdfPath = $this->pdf_save_path."pdf_".Str::uuid()->toString().".pdf";
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
     * 返回处理后的imagick对象
     * @return Imagick
     */
    public function getIm():Imagick
    {
        return $this->im;
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
     * 图片合成 gif
     * @param array $images
     * @param int $delay
     * @param string|null $transition fade|rotate
     * @param int $transitionSteps
     * @return $this
     */
    public function imagesToGif(array $images, int $delay = 20, string $transition = null, int $transitionSteps = 10): self
    {
        try {
            $this->im = new Imagick();
            $this->im->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256*2048*2048); //内存限制
            $this->im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256*2048*2048); //内存映射限制
            $this->im->setResourceLimit(Imagick::RESOURCETYPE_THREAD, 10); //线程数量
            $this->im->setFormat('gif');
            $previousImage = null;
            foreach ($images as $index => $frameFile) {
                if (!file_exists($frameFile)) continue;
                $currentImage = new Imagick($frameFile);
                $currentImage->setImageDelay($delay);
                if ($index > 0 && $transition && $previousImage) {
                    $this->imageTransition($previousImage, $currentImage, $transition, $transitionSteps);
                }
                $this->im->addImage($currentImage);
                $previousImage =clone $currentImage;
                $currentImage->clear();
                $currentImage->destroy();
            }
            $this->im->setImageFormat('gif');
            return $this;
        } catch (ImagickException $e) {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 图片转场效果
     * @param $previousImage
     * @param $currentImage
     * @param $transition
     * @param $transitionSteps
     * @return void
     * @throws ImagickException
     */
    protected function imageTransition(Imagick $previousImage,Imagick &$currentImage,string $transition, int $transitionSteps)
    {
        // 统一尺寸，避免转场错位
        $w = $previousImage->getImageWidth();
        $h = $previousImage->getImageHeight();
        if ($currentImage->getImageWidth() != $w || $currentImage->getImageHeight() != $h) {
            $currentImage->scaleImage($w, $h);
        }
        $parallel = null;
        if(Coroutine::inCoroutine())
        {
            $parallel = new Parallel($this->maxParallel);

        }
        for ($i = 1; $i <= $transitionSteps; $i++) {
            $progress = $i / ($transitionSteps + 1);
            $frame = clone $previousImage;
            $overlay = clone $currentImage;
            if($parallel)
            {
                $parallel->add(function()use($transition,$progress,$frame,$overlay,$w,$h){

                    return $this->handleTransition($transition,$overlay,$progress,$frame,$w,$h);
                });
            }else{
                $frame = $this->handleTransition($transition,$overlay,$progress,$frame,$w,$h);
                $this->im->addImage($frame);
                $frame->clear();
                $frame->destroy();
            }
        }
        if($parallel)
        {
            try{
                $processImages = $parallel->wait();
                foreach($processImages as $image)
                {
                    $this->im->addImage($image);
                    $image->clear();
                    $image->destroy();
                }
            } catch(ParallelExecutionException $e){
                throw new PdfImagesException($e->getMessage());
            }
        }
    }

    protected function handleTransition(string $transition,Imagick $overlay, float $progress,Imagick $frame,$w=null,$h=null)
    {
        switch ($transition)
        {
            case "fade":
                if (method_exists($overlay, 'setImageAlpha')) {
                    $overlay->setImageAlpha($progress);
                } else{
                    $overlay->evaluateImage(Imagick::EVALUATE_MULTIPLY, $progress, Imagick::CHANNEL_ALPHA);
                }
                $frame->compositeImage($overlay, Imagick::COMPOSITE_BLEND, 0, 0);
                break;
            case "rotate":
                $angle = 360 * (1 - $progress); // 从360度转到0度
                // 缩放和旋转
                $overlay->scaleImage((int)($w * $progress), (int)($h * $progress));
                $overlay->rotateImage(new ImagickPixel('transparent'), $angle);
                // 居中合成
                $ox = ($w - $overlay->getImageWidth()) / 2;
                $oy = ($h - $overlay->getImageHeight()) / 2;
                $frame->compositeImage($overlay, Imagick::COMPOSITE_OVER, (int)$ox, (int)$oy);
                break;
        }
        $frame->setImageDelay(5);
        $overlay->clear();
        return $frame;
    }

    /**
     * 返回二进制流
     * @return string
     */
    public function toBlod()
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        // 对于多帧图像（如 GIF），使用 getImagesBlob
        if ($this->im->getNumberImages() > 1) {
            $blob = $this->im->getImagesBlob();
        } else {
            $blob = $this->im->getImageBlob();
        }
        $this->im->clear();
        $this->im->destroy();
        return $blob;
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
        
        // 修复：不再使用 getImage() 取单帧，而是直接操作 $this->im
        $this->im->setImageFormat($imgExt);
        $newPath = $dir . 'out_'.Str::uuid()->toString().'.'.$imgExt;

        if ($this->im->getNumberImages() > 1) {

            $res = $this->im->writeImages($newPath, true);
        } else {
            $res = $this->im->writeImage($newPath);
        }
        $this->im->clear();
        $this->im->destroy();
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
     * @return $this
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
     * @return $this
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
     * 图片锐化
     * @param float $radius
     * @param float $sigma
     * @return $this
     */
    public function sharpen(float $radius = 0, float $sigma = 1)
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        try {
            $this->im->sharpenImage($radius, $sigma);
            return $this;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 图片模糊
     * @param float $radius
     * @param float $sigma
     * @return $this
     */
    public function blur(float $radius = 0, float $sigma = 1)
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        try {
            $this->im->blurImage($radius, $sigma);
            return $this;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 图片翻转
     * @param string $mode vertical|horizontal
     * @return $this
     */
    public function flip(string $mode = 'vertical')
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        try {
            if ($mode === 'horizontal' || $mode === 'h') {
                $this->im->flopImage(); // 水平翻转
            } else {
                $this->im->flipImage(); // 垂直翻转
            }
            return $this;
        }catch (ImagickException $e)
        {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 图片旋转
     * @param float $degrees
     * @param string $background
     * @return $this
     */
    public function rotate(float $degrees, $background = 'transparent')
    {
        if(empty($this->im)) throw new PdfImagesException("请先调用 openImage() 加载图片");
        try {
            $this->im->rotateImage(new ImagickPixel($background), $degrees);
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

    /**
     * 图片拼接
     * @param array $images 图片路径数组
     * @param int $spacing 间距
     * @param string $direction vertical|v|horizontal|h 拼接方向
     * @param string $background_color 背景颜色
     * @return $this
     * @throws PdfImagesException
     */
    public function combineImages(array $images, string $direction = 'v', int $spacing = 0, string $background_color = 'white'): self
    {
        if (empty($images)) {
            throw new PdfImagesException("图片数组不能为空");
        }
        try {
            $imgObjects = [];
            $totalWidth = 0;
            $totalHeight = 0;
            $maxWidth = 0;
            $maxHeight = 0;

            // 预加载所有图片并计算尺寸
            foreach ($images as $file) {
                if (!file_exists($file)) continue;
                $img = new Imagick($file);
                $w = $img->getImageWidth();
                $h = $img->getImageHeight();
                
                $imgObjects[] = [
                    'obj' => $img,
                    'w' => $w,
                    'h' => $h
                ];
                if ($w > $maxWidth) $maxWidth = $w;
                if ($h > $maxHeight) $maxHeight = $h;
                
                $totalWidth += $w;
                $totalHeight += $h;
            }
            if (empty($imgObjects)) {
                throw new PdfImagesException("没有有效的图片可供拼接");
            }
            $count = count($imgObjects);
            $totalSpacing = ($count - 1) * $spacing;
            if ($totalSpacing < 0) $totalSpacing = 0;
            switch ($direction)
            {
                case 'vertical':
                case 'v':
                    $canvasWidth = $maxWidth;
                    $canvasHeight = $totalHeight + $totalSpacing;
                    break;
                case 'horizontal':
                case 'h':
                default:
                    $canvasWidth = $totalWidth + $totalSpacing;
                    $canvasHeight = $maxHeight;
                    break;
            }
            // 创建画布
            $canvas = new Imagick();
            $canvas->newImage($canvasWidth, $canvasHeight, new ImagickPixel($background_color));
            // 尝试继承第一张图片的格式，默认 jpg
            $format = 'jpg';
            if (isset($imgObjects[0]['obj'])) {
                try {
                    $format = $imgObjects[0]['obj']->getImageFormat();
                } catch (\Throwable $e) {}
            }
            $canvas->setImageFormat($format);
            // 拼接
            $currentX = 0;
            $currentY = 0;
            foreach ($imgObjects as $item) {
                /** @var Imagick $img */
                $img = $item['obj'];
                $w = $item['w'];
                $h = $item['h'];

                if ($direction === 'horizontal' || $direction === 'h') {
                     // 水平拼接，垂直居中
                    $y = ($canvasHeight - $h) / 2;
                    $canvas->compositeImage($img, Imagick::COMPOSITE_DEFAULT, $currentX, (int)$y);
                    $currentX += $w + $spacing;
                } else {
                    // 垂直拼接，水平居中
                    $x = ($canvasWidth - $w) / 2;
                    $canvas->compositeImage($img, Imagick::COMPOSITE_DEFAULT, (int)$x, $currentY);
                    $currentY += $h + $spacing;
                }
                $img->clear();
                $img->destroy();
            }
            $this->im = $canvas;
            return $this;
        } catch (ImagickException $e) {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 图片合成照片墙
     * @param array $images 图片路径数组
     * @param array $options 配置选项
     *  - width: 缩略图宽度
     *  - height: 缩略图高度
     *  - cols: 每行图片张数
     *  - gap: 图片间隙
     *  - gap_color: 间隙背景色 (default: transparent)
     *  - angle: 旋转角度 (default: 0)
     * @return $this
     * @throws PdfImagesException
     */
    public function createPhotoWall(array $images, array $options = []): self
    {
        if (empty($images)) {
            throw new PdfImagesException("图片数组不能为空");
        }

        $thumbW = $options['width'] ?? 200;
        $thumbH = $options['height'] ?? 200;
        $cols = $options['cols'] ?? 3;
        $gap = $options['gap'] ?? 10;
        $gapColor = $options['gap_color'] ?? 'transparent';
        $angle = $options['angle'] ?? 0;

        try {
            $processedImages = [];
            $maxCellW = 0;
            $maxCellH = 0;

            foreach ($images as $file) {
                if (!file_exists($file)) continue;

                $img = new Imagick($file);
                // 裁剪并缩放到指定大小
                $img->cropThumbnailImage($thumbW, $thumbH);

                // 旋转
                if ($angle != 0) {
                    $img->rotateImage(new ImagickPixel('transparent'), $angle);
                }

                $w = $img->getImageWidth();
                $h = $img->getImageHeight();

                if ($w > $maxCellW) $maxCellW = $w;
                if ($h > $maxCellH) $maxCellH = $h;

                $processedImages[] = $img;
            }

            if (empty($processedImages)) {
                throw new PdfImagesException("没有有效的图片");
            }

            $count = count($processedImages);
            $rows = (int)ceil($count / $cols);

            // 计算画布大小
            $canvasH = ($rows * $maxCellH) + (($rows - 1) * $gap);

            // 修正：如果计算出的宽高小于单个格子（例如 cols > count），应该按实际占用来？
            // 不，照片墙通常是固定宽度的容器。这里我们根据 cols 撑开。
            // 如果只有 1 张图，cols=3，画布还是会宽。
            // 修正：如果是为了生成一张图，通常希望画布紧凑。
            // 如果 count < cols，实际列数是 count。
            $actualCols = min($count, $cols);
            $canvasW = ($actualCols * $maxCellW) + (($actualCols - 1) * $gap);


            $canvas = new Imagick();
            $canvas->newImage($canvasW, $canvasH, new ImagickPixel($gapColor));
            $canvas->setImageFormat('png'); // 默认使用 png 以支持透明

            $col = 0;
            $row = 0;

            foreach ($processedImages as $img) {
                $x = $col * ($maxCellW + $gap);
                $y = $row * ($maxCellH + $gap);

                // 居中放置在格子中
                $iw = $img->getImageWidth();
                $ih = $img->getImageHeight();
                $offX = ($maxCellW - $iw) / 2;
                $offY = ($maxCellH - $ih) / 2;

                $canvas->compositeImage($img, Imagick::COMPOSITE_DEFAULT, (int)($x + $offX), (int)($y + $offY));

                $img->clear();
                $img->destroy();

                $col++;
                if ($col >= $cols) {
                    $col = 0;
                    $row++;
                }
            }

            $this->im = $canvas;
            return $this;

        } catch (ImagickException $e) {
            throw new PdfImagesException($e->getMessage());
        }
    }

    /**
     * 指定位置合成图片或文字到 PDF
     * @param string $pdfPath PDF 路径
     * @param array $data 配置数组
     * [
     *   {
     *      'index' => 1, // 页码
     *      'position' => [
     *          ['type'=>'text', 'text'=>'内容', 'x'=>10, 'y'=>10, 'size'=>12, 'color'=>'red', 'font'=>'msyh.ttf'],
     *          ['type'=>'image', 'src'=>'./logo.png', 'x'=>100, 'y'=>100, 'w'=>50, 'h'=>50, 'angle'=>0]
     *      ]
     *   }
     * ]
     * @param string|null $savePath 保存路径
     * @return string
     * @throws PdfImagesException
     */
    public function modifyPdf(string $pdfPath, array $data, string $savePath = null): string
    {
        if (!file_exists($pdfPath)) {
            throw new PdfImagesException("PDF文件不存在");
        }
        // 确定保存路径
        if (!empty($savePath)) {
            $this->pdf_save_path = $savePath;
        }
        $this->mkdirPath($this->pdf_save_path);
        $parallel = null;
        if(Coroutine::inCoroutine())
        {
            $parallel = new Parallel($this->maxParallel);
        }
        try {
            $processImages = $this->pdfToImages($pdfPath);
            foreach ($data as $pageConfig) {
                $pageIndex = ($pageConfig['index'] ?? 1) - 1;
                if(!isset($processImages[$pageIndex]))  continue;

                $positions = $pageConfig['position'] ?? [];
                $handleImgPath = $processImages[$pageIndex];
                if($parallel)
                {
                    $parallel->add(function()use($positions,$handleImgPath,$pageIndex,$processImages){
                        $handleImage = new Imagick($handleImgPath);
                        foreach ($positions as $item) {
                            $type = $item['type'] ?? '';
                            $x = (int)($item['x'] ?? 0);
                            $y = (int)($item['y'] ?? 0);
                            if ($type === 'text') {
                                $this->drawTextItem($handleImage, $item, $x, $y);
                            } elseif ($type === 'images' || $type === 'image') {
                                $this->drawImageItem($handleImage, $item, $x, $y);
                            }
                        }
                        $saveHandleImg = $this->pdf_save_path."current_".$pageIndex.'_'.Str::uuid().".jpeg";
                        $handleImage->writeImage($saveHandleImg);
                        $handleImage->clear();
                        $handleImage->destroy();
                        @unlink($processImages[$pageIndex]);
                        return [$pageIndex, $saveHandleImg];
                    });
                }else{
                    $handleImage = new Imagick($handleImgPath);
                    foreach ($positions as $item) {
                        $type = $item['type'] ?? '';
                        $x = (int)($item['x'] ?? 0);
                        $y = (int)($item['y'] ?? 0);
                        if ($type === 'text') {
                            $this->drawTextItem($handleImage, $item, $x, $y);
                        } elseif ($type === 'images' || $type === 'image') {
                            $this->drawImageItem($handleImage, $item, $x, $y);
                        }
                    }
                    $saveHandleImg = $this->pdf_save_path."current_".$pageIndex.'_'.Str::uuid().".jpeg";
                    $handleImage->writeImage($saveHandleImg);
                    $handleImage->clear();
                    $handleImage->destroy();
                    @unlink($processImages[$pageIndex]);
                    $processImages[$pageIndex] =$saveHandleImg;
                }
            }
            if($parallel)
            {
                try{
                    $processCoImages = $parallel->wait();
                    foreach($processCoImages as $handleImg)
                    {
                        list($index,$handleImgPath) = $handleImg;
                        $processImages[$index] =$handleImgPath;
                    }
                } catch(ParallelExecutionException $e){
                    throw new PdfImagesException($e->getMessage());
                }
            }
            $savePath = $this->imagesToPdf($processImages,$this->pdf_save_path);
            foreach ($processImages as $filePath) @unlink($filePath);
            return $savePath;
        } catch (ImagickException $e) {
            throw new PdfImagesException($e->getMessage());
        }
    }

    protected function drawTextItem(Imagick $im, array $item, int $x, int $y)
    {
        $text = $item['text'] ?? ($item['content'] ?? '');
        if ($text === '') return;

        $draw = new ImagickDraw();
        $color = $item['color'] ?? 'black';
        $font = $item['font'] ?? null;
        $size = $item['size'] ?? 12;
        $width = $item['width'] ?? 200;
        $height = $item['height'] ?? 80;
        if ($width > 0 && $height > 0) {
            $draw->rectangle($x, $y, $x + $width, $y + $height);
        }
        $draw->setFillColor(new ImagickPixel($color));
        $draw->setFontSize($size);
        if ($font && file_exists($font)) {
            $draw->setFont($font);
        }
        $draw->setTextAntialias(true);
        $im->annotateImage($draw, $x, $y, 0, $text);
    }

    protected function drawImageItem(Imagick $im, array $item, int $x, int $y)
    {
        $src = $item['src'] ?? '';
        if (!file_exists($src)) throw new PdfImagesException("附件{$src}不存在");
        $overlay = new Imagick($src);
        $width = $item['width'] ?? 0;
        $height = $item['height'] ?? 0;
        $angle = $item['angle'] ?? 0;
        if ($width > 0 && $height > 0) {
            $overlay->scaleImage($width, $height);
        }
        if ($angle != 0) {
            $overlay->rotateImage(new ImagickPixel('transparent'), $angle);
        }
        $im->compositeImage($overlay, Imagick::COMPOSITE_DEFAULT, $x, $y);
        $overlay->clear();
        $overlay->destroy();
    }
}
