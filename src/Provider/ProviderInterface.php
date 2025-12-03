<?php

namespace Wudg\PdfImages\Provider;
use Wudg\PdfImages\Engine\Engine;
interface ProviderInterface
{
    public function make(string $name): Engine;
}