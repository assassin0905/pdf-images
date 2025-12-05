<?php
/**
 * Notes:
 * User: wudg <544038230@qq.com>
 * Date: 2025/12/3 09:26
 */

namespace Wudg\PdfImages\Provider;


use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Wudg\PdfImages\Engine\PdfImagesEngine;
use Wudg\PdfImages\Engine\ImagickEngine;

class ImagickProvider implements ProviderInterface
{

    public function __construct(private ContainerInterface $container)
    {
    }

    public function make(string $name): PdfImagesEngine
    {
        $config = $this->container->get(ConfigInterface::class)->get('pdf-images');

        $config = array_merge([
            'save_img_path' => $config['save_img_path'],
            'save_pdf_path' => $config['save_pdf_path'],
        ],$config['engine'][$name] ?? []);

        return new ImagickEngine($config);
    }
}