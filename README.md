# pdf 转换图片或者图片生成 pdf

##  安装
```
composer require wudg/pdf-images
```
## 前置条件
依赖php扩展 imagick,bcmath
### 安装 ghostscript
```
## 安装依赖 ghostscript
# Alpine
apk add ghostscript ghostscript-fonts
# Ubuntu / Debian
sudo apt-get update && sudo apt-get install -y ghostscript

#CentOS / Rocky / Alma
sudo yum install -y ghostscript

# MacOS
brew install ghostscript
```

### 安装 pdfinfo (非必须，安装后会对处理多页 pdf 文件占用大量内存有优化)
```
# Ubuntu / Debian
sudo apt-get install poppler-utils

# CentOS / Rocky / Alma
sudo yum install poppler-utils

# Alpine
apk add poppler-utils

# MacOS
brew install poppler

```


## 参数配置说明
- `save_img_path`: 生成的图片保存路径,绝对路径
- `save_pdf_path`: 生成的pdf文件保存路径,绝对路径
- `engine.imagick.dpi`: 渲染 DPI,越高细节越好，内存占用也越高，默认 300
- `engine.imagick.width`: 默认生成图片宽度，宽度保持统一高度自适应,默认 1191
- `engine.imagick.compression_quality`: 压缩质量，0-100，越高质量越好,默认 100
- `engine.imagick.ext`: pdf 生成的图片后缀名称，支持 png,jpeg, 默认 jpeg


## 使用
此插件支持在 hyperf 框架下运行 也可以脱离hyperf 框架使用，测试环境为 hyperf 3.1.10, 非 hyperf 框架会安装 "hyperf/contract","hyperf/support" 两个组件

### 在 hyperf 框架下使用
#### 注解模式
```
<?php
/**
 * Notes:
 * User: wudg <544038230@qq.com>
 * Date: 2025/12/03 16:57
 */

namespace App\Controller;
use Wudg\PdfImages\Engine\PdfImagesEngine;
use Hyperf\Di\Annotation\Inject;

class TestController extends AbstractController 
{
    #[Inject]
    private PdfImagesEngine $pdfImage;

    public function testPdfToImg() 
    {
        $pdfPath = BASE_PATH.'/test.pdf';
        $images = $this->pdfImage->pdfToImages($pdfPath);
        print_r($images);
    }
}
```


### 非 hyperf 框架下使用
```
use Wudg\PdfImages\Engine\ImagickEngine;

$config = [
    'save_img_path' => __DIR__.'/cache/images/'.date("Y/md/"), //生成的图片保存路径,相对根目录
    'save_pdf_path' => __DIR__.'/cache/pdf/'.date("Y/md/"), //生成的pdf保存路径,相对根目录
    'dpi' => 300, //渲染 DPI,越高细节越好，内存占用也越高
    'width' => 1191, //默认生成图片宽度，宽度保持统一高度自适应
    'compression_quality' => 100, //压缩质量，0-100，越高质量越好
    'ext' => 'jpeg', //pdf 生成的图片后缀名称，支持 png,jpeg
]
$engine = new ImagickEngine($config);

## pdf 转换为图片
$pdf = __DIR__.'/pdf/test.pdf';
$images = $engine->pdfToImages($pdf);
print_r($images);


## 图片合成 pdf
$pdfPath = $engine->imagesToPdf([
    __DIR__.'/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
    __DIR__.'/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
    __DIR__.'/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
    __DIR__.'/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
    __DIR__.'/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
]);
print_r($pdfPath);

```




## 实际效果
### 测试 5 页 pdf 转换图片fpm和携程下差距
```
# PHPUnit 10.5.59 by Sebastian Bergmann and contributors.
# Runtime:       PHP 8.3.19
# Configuration: /var/www/html/pdf-images/phpunit.xml


# fpm 模式下 Time: 00:11.236, Memory: 10.00 MB
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testPdfToImg'

# co 模式下 Time: 00:03.527, Memory: 10.00 MB
 ./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testCoPdfToImg'

```

### 测试 5张图片生成 pdf fpm和携程下差距,图片张数少时 时间差不多，大量图片没做测试
```
# fpm 模式下 Time: 00:00.640, Memory: 10.00 MB
 ./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testImgToPdf'
# co 模式下 Time: 00:00.637, Memory: 10.00 MB
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testCoImgToPdf'
```