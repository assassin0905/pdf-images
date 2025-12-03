<?php

namespace Wudg\PdfImages\Interface;

interface HandleInterface
{
     public function pdfToImages(string $pdfPath, string $savePath = null, array $options = []): array;

     public function imagesToPdf(array $images,string $savePath = null): string;
}