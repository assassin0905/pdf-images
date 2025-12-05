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

use Wudg\PdfImages\Engine\ImagickEngine;
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
        $engine = new ImagickEngine([]);
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
            $engine = new ImagickEngine($config);
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
        $engine = new ImagickEngine($config);

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
            $engine = new ImagickEngine($config);
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
        $engine = new ImagickEngine($config);
        $images = $engine->pdfToImages($pdf);
        $this->assertIsArray($images);
        $this->assertNotEmpty($images);
        foreach ($images as $img) {
            $this->assertFileExists($img);
            $this->assertStringEndsWith('.jpg', $img);
            $this->assertGreaterThan(0, filesize($img));
        }

    }

    public function testWatermarkText()
    {
        $engine = new ImagickEngine([
            'save_img_path' => __DIR__.'/../../src/cache/images/'.date('Y/md/'),
            'save_pdf_path' => __DIR__.'/../../src/cache/pdf/'.date('Y/md/'),
            'dpi' => 300,
            'width' => 1191,
            'compression_quality' => 100,
            'ext' => 'jpeg',
        ]);

        $src = __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg';
        if (!file_exists($src)) {
            $this->markTestSkipped('缺少样例图片 src/cache/images/...');
        }

        $dst = $engine->watermarkText($src, 'hello world 好！', [
            'size' => 36, // 字体大小
            'color' => 'rgba(255,0,0,0.5)', // 颜色
            'angle' => 0, // 旋转角度
            'position' => 'center', // 位置
            'offset_x' => 20, // 偏移 X
            'offset_y' => 20, // 偏移 Y
        ], dirname($src));
        $this->assertIsString($dst);
        $this->assertFileExists($dst);
        $this->assertGreaterThan(0, filesize($dst));
        $this->assertNotEquals(realpath($src), realpath($dst));
    }


    public function testMoreText()
    {
        $src = __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg';
        $overlay = __DIR__.'/../../cache/images/demo.png';

        if (!file_exists($src) || !file_exists($overlay)) {
            $this->markTestSkipped('缺少样例图片 src/cache/images/...');
        }
        $engine = new ImagickEngine();
        $source = $engine->openImage($src);
        $pointData = [
            ['text'=>'左上','option'=>['position'=>'left_top', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(255,0,0,0.4)']],
            ['text'=>'上中','option'=>['position'=>'top', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(255,0,0,0.4)']],
            ['text'=>'右上','option'=>['position'=>'right_top', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(255,0,0,0.4)']],
            ['text'=>'左中','option'=>['position'=>'left_center', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(0,255,0,0.4)']],
            ['text'=>'中','option'=>['position'=>'center', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(0,255,0,0.4)']],
            ['text'=>'右中','option'=>['position'=>'right_center', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(0,255,0,0.4)']],
            ['text'=>'左下','option'=>['position'=>'left_down', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(0,0,255,0.4)']],
            ['text'=>'下','option'=>['position'=>'down', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(0,0,255,0.4)']],
            ['text'=>'右下','option'=>['position'=>'right_down', 'offset_x' => 0, 'offset_y' => 0,'color' => 'rgba(0,0,255,0.4)']]
        ];
        foreach ($pointData as $text)
        {
            $source->addText($text['text'],array_merge([
                'size' => 24,
                'font' => __DIR__."/../../fonts/msyh.ttf",
            ],$text['option']));
        }
        $out = $source->toPath();
        $this->assertIsString($out);
        $this->assertFileExists($out);
        $this->assertGreaterThan(0, filesize($out));
        $this->assertNotEquals(realpath($src), realpath($out));
    }

    public function testChainEdit()
    {
        $src = __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg';
        $overlay = __DIR__.'/../../cache/images/demo.png';

        if (!file_exists($src) || !file_exists($overlay)) {
            $this->markTestSkipped('缺少样例图片 src/cache/images/...');
        }
        $engine = new ImagickEngine([]);
        $out = $engine
            ->openImage($src)
            ->addText('hello 世界', [
                'size' => 24,
                'font' => __DIR__."/../../fonts/msyh.ttf",
                'color' => 'rgba(0,0,0,0.4)',
                'position' => 'down',
                'offset_x' => 80,
                'offset_y' => 80,
            ])
            ->mergeImage($overlay, [
                'position' => 'center',
                'width' => 300,
                'keep_aspect' => true,
                'offset_x' => 0,
                'offset_y' => 0,
            ])
            ->resize(1000,1000)
            ->crop(500, 500, 200, 300)
            ->toPath();
        $this->assertIsString($out);
        $this->assertFileExists($out);
        $this->assertGreaterThan(0, filesize($out));
        $this->assertNotEquals(realpath($src), realpath($out));
    }

    public function testChainEffects()
    {
        $src = __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg';
        if (!file_exists($src)) {
            $this->markTestSkipped('缺少样例图片 src/cache/images/...');
        }
        $engine = new ImagickEngine([]);
        $out = $engine
            ->openImage($src)
            /**
             * $radius 控制锐化效果的扩散范围,通常 0.0 到 10.0，值越小：锐化效果更精细，只影响边缘附近的像素 越大 锐化效果更扩散，影响更大范围的像素
             * $sigma 控制锐化的强度或"锐利度" 通常 0.0 到 10.0,值越小：锐化效果越柔和、轻微,越大 锐化效果越强烈、明显
             * 给定几个参考区间  (0.5, 1) 轻微、精细的锐化,(2.0, 1) 中等范围的锐化,(5.0, 1) 中等范围的锐化
             */
//            ->sharpen(5.0, 1.5) // 锐化
            /**
             * $radius 控制模糊效果的采样半径,通常 1.0 到 20.0 之间 值越小：轻微模糊，保持较多细节,越大：强烈模糊，效果更明显
             * $sigma 控制高斯分布的标准差，通常 0.5 到 10.0 之间 决定模糊的"平滑度",值越小：模糊更集中，边缘相对保留,越大：模糊更平滑、更自然
             */
//            ->blur(5, 3) // 高斯模糊
            /**
             * $mode  vertical 水平翻转，默认值, horizontal | h 垂直翻转
             */
//            ->flip('h') // 翻转
            /**
             * $angle 旋转角度
             * $backgroud 填充背景颜色，默认白色 支持 rgba(255, 0, 0, 0.5), (white,red) 或者 #EFEFEF
             */
            ->rotate(45,'#EFEFEF') //旋转
            ->toPath();
        $this->assertIsString($out);
        $this->assertFileExists($out);
        $this->assertGreaterThan(0, filesize($out));
        $this->assertNotEquals(realpath($src), realpath($out));
    }
}
