<?php

namespace Wudg\PdfImages\Engine;
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
abstract class PdfImagesEngine
{


    abstract public function pdfToImages(string $pdfPath, string $savePath = null, array $options = []): array;


    abstract public function imagesToPdf(array $images,string $savePath = null): string;
}