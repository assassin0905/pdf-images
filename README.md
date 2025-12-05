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
```
# 发布配置文件
php bin/hyperf.php vendor:publish wudg/pdf-images
```
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
此种方式也适合 hyperf 框架使用，配置文件初始化时给到，然后直接调用也会走携程模式来处理 pdf 转换图片
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

## 图片相关操作
图片操作采用链式调用设计，易于理解 需要什么调用什么，最后支持返回 二进制，base64 或者直接保存到本地返回路径

### 基础方法
```
<?php


use Wudg\PdfImages\Engine\ImagickEngine;


$engine = new ImagickEngine();

$demoImg = __DIR__.'/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg';
$mergeImg = __DIR__.'/../cache/images/demo.png';
$engine->openImage($demoImg) //打开资源图片
/**
 * 添加文字
 * size     : 字体大小
 * font     : 字体文件路径,如果文字有中文，需要字体库支持，否则中文不显示
 * color    : 颜色配置，默认60% 不透明度的白色，rgba(255,255,255,0.6)，
 * angle    : 旋转角度
 * position : 定位位置，可选值 left_top(左上), top(上中), right_top(右上), left_center(左中), center(中), right_center(右中), left_down(左下), down(下), right_down(右下)
 * offset_x : x轴偏移量, 定位起始点相对于 postion 设置后的位置，比如设置 right_down(右下角) 的时候，起始定位位置为图片的右下角
 * offset_y : y轴偏移量
 */
->addText("hello 世界",[
    'size' => 36,
    'font' => __DIR__."/../../fonts/msyh.ttf",
    'color' => 'rgba(255,0,0,0.5)', 
    'angle' => 0, 
    'position' => 'down',
    'offset_x' => 10,
    'offset_y' => 10,
])
/**
 *  图片缩放
 * $width 宽度 缩放后的图片宽度
 * $height 高度 缩放后的图片高度,高度可以不传，不传时，图片会保持原比例缩放
 * $keep_aspect 是否保持图片比例，保持图片比例会自动根据宽度或者高度其中一个按比例缩放, 默认 true
 */
->resize(600,null,true)
 /**
  * 合并图片
  * image_path   : 需要合并的图片路径
  * position     : 合并位置，可选值与addText 一样
  * offset_x     : x轴偏移量
  * width        : 合并图片缩放宽度,height 如果没有设置，自动按比例计算
  * height       : 合并图片缩放高度,width 如果没有设置，自动按比例计算
  * offset_x     : y轴偏移量
  * keep_aspect  : 是否保持图片比例，保持图片比例会自动根据宽度或者高度其中一个按比例缩放, 默认 true
  */
->mergeImage($mergeImg, [
    'position' => 'right_down',
    'width' => 200,
    'keep_aspect' => true,
    'offset_x' => 10,
    'offset_y' => 10,
])
 /**
  * 合并裁剪
  * width      :  裁剪宽度
  * height     :  裁剪高度
  * x          :  x轴偏移量,相对于图片左上角位置
  * y          :  y轴偏移量,相对于图片左上角位置
  */
->crop(300, 300, 0, 0)
->toPath(); //保存为路径
//->toBase64() //返回 base64
//->toBlod() //返回二进制

```
### 你也可以一次打点多个文本到同一张图片中，想怎么来怎么来
```
$src = __DIR__.'/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg';
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

# 实测效果，上面向单张图片9个方位 添加了文字，并保存为图片耗时如下
# ./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testMoreText'
# Runtime:       PHP 8.3.19
# Time: 00:00.336, Memory: 10.00 MB

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

### 图片操作
```
### 测试处理图片，文字水印 & 合并图片 & 缩放图片 & 裁剪图片 最终保存
# Time: 00:00.308, Memory: 10.00 MB
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testChainEdit'
```