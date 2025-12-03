<?php
/**
 * Notes:
 * User: wudg <544038230@qq.com>
 * Date: 2025/12/3 09:23
 */

namespace Wudg\PdfImages\Engine;
use Hyperf\Stringable\Str;
use Imagick;
use ImagickException;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Wudg\PdfImages\Exception\PdfImagesException;

class ImagickEngine extends Engine
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

    protected Imagick $im;

    /**
     * 最大开启携程上数量
     * @var int
     */
    protected int $maxParallel = 5;


    /**
     * 生成图片保存路径
     * @var string|mixed
     */
    protected string $images_save_path = 'cache/images/';


    /**
     * 生成 pdf 保存路径
     * @var string
     */
    protected string $pdf_save_path = 'cache/pdf/';


    public function __construct(array $config)
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
        $this->im = new Imagick();
    }

    public function pdfToImages(string $pdfPath, string $savePath = null, array $options = []): array
    {
        if($savePath)
        {
            $this->images_save_path = $savePath;
        }else{
            $this->images_save_path = BASE_PATH.DIRECTORY_SEPARATOR.$this->config['save_img_path'];
        }
        $this->images_save_path = rtrim($this->images_save_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if(!is_dir($this->images_save_path))  mkdir($this->images_save_path,0777,true);
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

    /**
     * FPM 模式执行
     * @param int $pdfPageNum
     * @param string $pdfPath
     * @return array
     * @throws ImagickException
     */
    public function handleFpm(int $pdfPageNum,string $pdfPath): array
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
            $this->pdf_save_path = BASE_PATH.DIRECTORY_SEPARATOR.$this->config['save_pdf_path'];
        }
        $this->pdf_save_path = rtrim($this->pdf_save_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        try {
            $pdf = $this->im;

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
}