<?php

namespace Wudg\PdfImages\Provider;
use Wudg\PdfImages\Engine\PdfImagesEngine;
interface ProviderInterface
{
    public function make(string $name): PdfImagesEngine;
}