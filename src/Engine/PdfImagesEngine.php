<?php

namespace Wudg\PdfImages\Engine;
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

/**
 *
 * @method openImage(string $file):$this
 * @method addText(string $text, array $options = []):$this
 * @method mergeImage(string $overlayPath, array $options = []):$this
 * @method crop(int $width, int $height, int $x, int $y):$this
 * @method resize(int $width, ?int $height = null, bool $keepAspect = true):$this
 * @method blur(float $radius = 0, float $sigma = 1):$this
 * @method flip(string $mode = 'vertical'):$this
 * @method rotate(float $degrees):$this
 * @method getIm():Imagick
 * @method sharpen(float $radius = 0, float $sigma = 1):$this
 * @method combineImages(array $images, string $direction = 'v', int $spacing = 0, string $background_color = 'white'):$this
 * @method imagesToGif(array $images, int $delay = 20):$this
 * @method toBlod()
 * @method toBase64()
 * @method toPath(string $savePath = null, string $ext = null)
 */
abstract class PdfImagesEngine
{

    /**
     * pdf 转换为图片
     * @param string $pdfPath
     * @param string|null $savePath
     * @param array $options
     * @return array
     */
    abstract public function pdfToImages(string $pdfPath, string $savePath = null, array $options = []): array;


    /**
     * 图片转换为pdf
     * @param array $images
     * @param string|null $savePath
     * @return string
     */
    abstract public function imagesToPdf(array $images,string $savePath = null): string;


    /**
     * 给图片添加文字
     * @param string $file
     * @param string $text
     * @param array $options
     * @param string|null $savePath
     * @return string
     */
    abstract public function watermarkText(string $file, string $text, array $options = [], string $savePath = null): string;
}