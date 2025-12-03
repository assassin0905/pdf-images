<?php
/**
 * Notes:
 * User: wudg <544038230@qq.com>
 * Date: 2025/12/3 10:04
 */

namespace Wudg\PdfImages;


use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Wudg\PdfImages\Engine\ImagesEngine;
use Wudg\PdfImages\Provider\ProviderInterface;
use function Hyperf\Support\make;

class EngineFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $name = $config->get('pdf-images.default', 'default');
        $driver = $config->get("pdf-images.engine.{$name}.driver", ImagesEngine::class);
        $driverInstance = make($driver);
        if ($driverInstance instanceof ProviderInterface) {
            return $driverInstance->make($name);
        }
        return $driverInstance;
    }

}