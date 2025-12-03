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

namespace HyperfTest\Cases;

use Wudg\PdfImages\Engine\ImagesEngine;
use function Swoole\Coroutine\run;
/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends AbstractTestCase
{
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testPdfNum()
    {
        $engine = new ImagesEngine([]);
        $pdf = __DIR__ . '/../../test.pdf';

        if (!file_exists($pdf)) {
            $this->markTestSkipped('缺少测试 PDF 文件 tests/test.pdf');
        }
        $pages = $engine->getPdfPages($pdf);
        $this->assertIsInt($pages);
        $this->assertGreaterThanOrEqual(0, $pages);
    }

    public function testCoImgToPdf()
    {
        if (!function_exists('\\Swoole\\Coroutine\\run')) {
            $this->markTestSkipped('缺少 Swoole 扩展，跳过协程测试');
        }

        $config = [
            'save_img_path' => __DIR__.'/../../src/cache/images/'.date('Y/md/'),
            'save_pdf_path' => __DIR__.'/../../src/cache/pdf/'.date('Y/md/'),
            'dpi' => 300,
            'width' => 1191,
            'compression_quality' => 100,
            'ext' => 'jpeg',
        ];

        run(function () use ($config) {
            $engine = new ImagesEngine($config);
            $pdfPath = $engine->imagesToPdf([
                __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
            ]);

            $this->assertIsString($pdfPath);
            $this->assertStringEndsWith('.pdf', $pdfPath);
            $this->assertFileExists($pdfPath);
            $this->assertGreaterThan(0, filesize($pdfPath));
        });
    }

    public function testImgToPdf()
    {
        $config = [
            'save_img_path' => __DIR__.'/../../src/cache/images/'.date('Y/md/'),
            'save_pdf_path' => __DIR__.'/../../src/cache/pdf/'.date('Y/md/'),
            'dpi' => 300,
            'width' => 1191,
            'compression_quality' => 100,
            'ext' => 'jpeg',
        ];
        $engine = new ImagesEngine($config);

        $pdfPath = $engine->imagesToPdf([
            __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
        ]);

        $this->assertIsString($pdfPath);
        $this->assertStringEndsWith('.pdf', $pdfPath);
        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }


    public function testCoPdfToImg()
    {
        if (empty(\Imagick::queryFormats('PDF'))) {
            $this->markTestSkipped('Imagick 未启用 PDF 读取支持（缺少 Ghostscript 或被 policy.xml 禁用）');
        }
        if (!function_exists('\\Swoole\\Coroutine\\run')) {
            $this->markTestSkipped('缺少 Swoole 扩展，跳过协程测试');
        }

        $config = [
            'save_img_path' => __DIR__.'/../../src/cache/images/'.date('Y/md/'),
            'save_pdf_path' => __DIR__.'/../../src/cache/pdf/'.date('Y/md/'),
            'dpi' => 300,
            'width' => 1191,
            'compression_quality' => 100,
            'ext' => 'jpg',
        ];

        run(function () use ($config) {
            $pdf = __DIR__ . '/../../test.pdf';
            if (!file_exists($pdf)) {
                $this->markTestSkipped('缺少测试 PDF 文件 tests/test.pdf');
            }
            $engine = new ImagesEngine($config);
            $images = $engine->pdfToImages($pdf);
            $this->assertIsArray($images);
            $this->assertNotEmpty($images);
            foreach ($images as $img) {
                $this->assertFileExists($img);
                $this->assertStringEndsWith('.jpg', $img);
                $this->assertGreaterThan(0, filesize($img));
            }
        });
    }

    public function testPdfToImg()
    {
        if (empty(\Imagick::queryFormats('PDF'))) {
            $this->markTestSkipped('Imagick 未启用 PDF 读取支持（缺少 Ghostscript 或被 policy.xml 禁用）');
        }
        $config = [
            'save_img_path' => __DIR__.'/../../src/cache/images/'.date('Y/md/'),
            'save_pdf_path' => __DIR__.'/../../src/cache/pdf/'.date('Y/md/'),
            'dpi' => 300,
            'width' => 1191,
            'compression_quality' => 100,
            'ext' => 'jpg',
        ];
        $pdf = __DIR__ . '/../../test.pdf';

        if (!file_exists($pdf)) {
            $this->markTestSkipped('缺少测试 PDF 文件 tests/test.pdf');
        }
        $engine = new ImagesEngine($config);
        $images = $engine->pdfToImages($pdf);
        $this->assertIsArray($images);
        $this->assertNotEmpty($images);
        foreach ($images as $img) {
            $this->assertFileExists($img);
            $this->assertStringEndsWith('.jpg', $img);
            $this->assertGreaterThan(0, filesize($img));
        }

    }
}
