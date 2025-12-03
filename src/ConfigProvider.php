<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Wudg\PdfImages;

use Wudg\PdfImages\Engine\PdfImagesEngine;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                PdfImagesEngine::class => EngineFactory::class,
            ],
            'commands' => [
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for pdf-images.',
                    'source' => __DIR__ . '/../publish/pdf-images.php',
                    'destination' => BASE_PATH . '/config/autoload/pdf-images.php',
                ],
            ],
        ];
    }
}
