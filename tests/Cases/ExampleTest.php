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

        $this->assertGreaterThan(0, $pages);
    }

    public function testCoImgToPdf()
    {
        $config = [

            'save_img_path' => 'cache/images/'.date("Y/md/"), //生成的图片保存路径,相对根目录
            'save_pdf_path' => 'cache/pdf/'.date("Y/md/"), //生成的pdf保存路径,相对根目录
            'dpi' => 300, //渲染 DPI,越高细节越好，内存占用也越高
            'width' => 1191, //默认生成图片宽度，宽度保持统一高度自适应
            'compression_quality' => 100, //压缩质量，0-100，越高质量越好
            'ext' => 'jpeg', //pdf 生成的图片后缀名称，支持 png,jpeg
        ];


        run(function()use($config) {
            $engine = new ImagickEngine($config);
            $pdfPath = $engine->imagesToPdf([
                __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
                __DIR__.'/../../src/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
            ]);
            var_dump($pdfPath);

        });
    }

    public function testImgToPdf()
    {
        $config = [
            'has_pdfinfo' => false, //是否安装pdfinfo,安装 pdfinfo 扩展后对超大pdf生成图片占用内存数量优势明显
            'save_img_path' => 'cache/images/'.date("Y/md/"), //生成的图片保存路径,相对根目录
            'save_pdf_path' => 'cache/pdf/'.date("Y/md/"), //生成的pdf保存路径,相对根目录
            'dpi' => 300, //渲染 DPI,越高细节越好，内存占用也越高
            'width' => 1191, //默认生成图片宽度，宽度保持统一高度自适应
            'compression_quality' => 100, //压缩质量，0-100，越高质量越好
            'ext' => 'jpeg', //pdf 生成的图片后缀名称，支持 png,jpeg
        ];
        $engine = new ImagickEngine($config);

        $pdfPath = $engine->imagesToPdf([
            __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
            __DIR__.'/../../src/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
        ]);

        $this->assertGreaterThan(0, $pdfPath);
    }


    public function testCoPdfToImg()
    {
        if (empty(\Imagick::queryFormats('PDF'))) {
            $this->markTestSkipped('Imagick 未启用 PDF 读取支持（缺少 Ghostscript 或被 policy.xml 禁用）');
        }
        $config = [
            'has_pdfinfo' => false, //是否安装pdfinfo,安装 pdfinfo 扩展后对超大pdf生成图片占用内存数量优势明显
            'save_img_path' => 'cache/images/'.date("Y/md/"), //生成的图片保存路径,相对根目录
            'save_pdf_path' => 'cache/pdf/'.date("Y/md/"), //生成的pdf保存路径,相对根目录
            'dpi' => 300, //渲染 DPI,越高细节越好，内存占用也越高
            'width' => 1191, //默认生成图片宽度，宽度保持统一高度自适应
            'compression_quality' => 100, //压缩质量，0-100，越高质量越好
            'ext' => 'jpg', //pdf 生成的图片后缀名称，支持 png,jpg
        ];


        run(function()use($config) {
            $pdf = __DIR__ . '/../../test.pdf';
            if (!file_exists($pdf)) {
                $this->markTestSkipped('缺少测试 PDF 文件 tests/test.pdf');
            }
            $engine = new ImagickEngine($config);
            $images = $engine->pdfToImages($pdf);
            print_r($images);
        });
    }

    public function testPdfToImg()
    {
        if (empty(\Imagick::queryFormats('PDF'))) {
            $this->markTestSkipped('Imagick 未启用 PDF 读取支持（缺少 Ghostscript 或被 policy.xml 禁用）');
        }
        $config = [
            'has_pdfinfo' => false, //是否安装pdfinfo,安装 pdfinfo 扩展后对超大pdf生成图片占用内存数量优势明显
            'save_img_path' => 'cache/images/'.date("Y/md/"), //生成的图片保存路径,相对根目录
            'save_pdf_path' => 'cache/pdf/'.date("Y/md/"), //生成的pdf保存路径,相对根目录
            'dpi' => 300, //渲染 DPI,越高细节越好，内存占用也越高
            'width' => 1191, //默认生成图片宽度，宽度保持统一高度自适应
            'compression_quality' => 100, //压缩质量，0-100，越高质量越好
            'ext' => 'jpg', //pdf 生成的图片后缀名称，支持 png,jpg
        ];
        $pdf = __DIR__ . '/../../test.pdf';

        if (!file_exists($pdf)) {
            $this->markTestSkipped('缺少测试 PDF 文件 tests/test.pdf');
        }
        $engine = new ImagickEngine($config);
        $images = $engine->pdfToImages($pdf);

        print_r($images);
//        run(function()use($config) {
//
//        });

    }
}
