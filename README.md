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
### 安装 imagick扩展
* **install detail**       http://blog.sixtymore.cn/archives/8/

```
# Alpine 
## 安装imagemagick
apk --update add imagemagick imagemagick-dev
## 安装依赖
apk add jpeg-dev libpng-dev freetype-dev imagemagick-dev

# 安装扩展
pecl install imagick
# 配置信息
cd /usr/local/etc/php/conf.d
vi docker-php-ext-imagick.ini
extension=imagick.so

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
  * 裁剪
  * width      :  裁剪宽度
  * height     :  裁剪高度
  * x          :  x轴偏移量,相对于图片左上角位置
  * y          :  y轴偏移量,相对于图片左上角位置
  */
->crop(300, 300, 0, 0)
/**
 * $radius 控制锐化效果的扩散范围,通常 0.0 到 10.0，值越小：锐化效果更精细，只影响边缘附近的像素 越大 锐化效果更扩散，影响更大范围的像素
 * $sigma 控制锐化的强度或"锐利度" 通常 0.0 到 10.0,值越小：锐化效果越柔和、轻微,越大 锐化效果越强烈、明显
 * 给定几个参考区间  (0.5, 1) 轻微、精细的锐化,(2.0, 1) 中等范围的锐化,(5.0, 1) 中等范围的锐化
 */
->sharpen(5.0, 1.5) // 锐化
/**
 * $radius 控制模糊效果的采样半径,通常 1.0 到 20.0 之间 值越小：轻微模糊，保持较多细节,越大：强烈模糊，效果更明显
 * $sigma 控制高斯分布的标准差，通常 0.5 到 10.0 之间 决定模糊的"平滑度",值越小：模糊更集中，边缘相对保留,越大：模糊更平滑、更自然
 */
->blur(5, 3) // 高斯模糊
/**
 * $mode  vertical 水平翻转，默认值, horizontal | h 垂直翻转
 */
 ->flip('h') // 翻转
/**
 * $angle 旋转角度
 * $backgroud 填充背景颜色，默认白色 支持 rgba(255, 0, 0, 0.5), (white,red) 或者 #EFEFEF
 */
->rotate(45,'#EFEFEF') //旋转
->toPath(); //保存为路径
//->toBase64() //返回 base64
//->toBlod() //返回二进制
```
### 你也可以一次打点多个文本到同一张图片中，想怎么来就怎么来
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

```
### 图片合成 gif 带转场效果
```
$images = [
    __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
];

$engine = new ImagickEngine([]);

/**
 *
 * $images      : 图片数组
 * $duration    : 帧间隔时间,50 表示 0.5s 
 * $transition  : 转场效果，可选值 rotate：旋转 | fade：渐变
 * $transition_duration : 转场帧数，默认 10,transition 有值才生效,建议 10以内，越大耗时越长，生成的 gif 图片越大
 */
$outRotate = $engine->imagesToGif($images, 50, 'rotate', 5)->toPath(null, 'gif');
```
### 图片拼接
```
$images = [
    __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
];
$engine = new ImagickEngine([]);
/**
 *
 * $images      : 图片数组
 * $direction   : 拼接方向 ，可选值 h|horizontal(水平) 或 vertical|v(垂直)
 * $spacing     : 拼接间隙，默认 0，无间隙
 * $background_color : 间隙背景色，默认 white
 */
$engine->combineImages($images, 'h', 20, 'blue')->toPath();

```
### 多张图合并成一张照片墙
```
$images = [
    __DIR__.'/../../src/cache/images/2025/1203/0_a2a52f0e-6ff4-4338-b48c-9c6739832bf8.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/1_535cacc6-3804-4134-93a5-c41b42f72a7e.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/2_7271b539-ce09-4356-89fd-20dede251c93.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/3_3f60489d-b606-42ec-9cd6-0bec79eb831c.jpg',
    __DIR__.'/../../src/cache/images/2025/1203/4_3503f27f-5766-416b-a3dd-6c32f3dd929f.jpg',
];
$engine = new ImagickEngine([]);

/**
 *
 * $images            : array 图片数组
 * $options.width     : int 缩略宽度
 * $options.height    : int 缩略高度
 * $options.cols      : int 图片摆放列数 
 * $options.gap       : int 每张图距离间隙 
 * $options.gap_color : string 间隙图片背景色 
 * $options.angle     : int 每张图旋转角度 
 */
$outH = $engine->createPhotoWall($images,[
    'width' => 150,
    'height' => 150,
    'cols' => 2,
    'gap' => 5,
    'gap_color' => 'white',
    'angle' => 30,
])->toPath();
```
### PDF 合成图片和文字内容，此方法在携程环境下效率很高，建议在携程环境使用
```
$data = [
    [
        'index' => 1, //页码
        'position' => [
            [
                'type' => 'image', // 写入类型，image（图片） | text(文字)
                'x' => 800,// 距离当前页数 x 轴距离
                'y' => 1200,// 距离当前页数 y 轴距离
                'width' => 80, // 图片宽度
                'height' => 80, // 图片高度
                'src' => $overlay, // 图片路径 type=image 有效 
            ]
        ]
    ],
    [
        'index' => 2,
        'position' => [
            [
                'type' => 'text',  // 写入类型，image（图片） | text(文字)
                'text' => '日期:'.date("Y-m-d H:i:s"), // 文字内容 type=text 有效 
                'font' => $fontPath,  // 字体路径 type=text 有效 
                'color' => 'rgba(255,0,0,0.5)',  // 字体颜色 type=text 有效 
                'x' => 160, // 距离当前页数 x 轴距离
                'y' => 80, // 距离当前页数 y 轴距离
                'size' => 40,   // 字号大小 type=text 有效 
                'width' => 100, // 区域宽度
                'height' => 80 // 区域高度
            ]
        ]
    ]
];
$pdfPath = __DIR__.'/../../src/cache/pdf/2025/1203/pdf_462f5502-27cb-484e-bf5f-dcc2e6388176.pdf';
$overlay = __DIR__.'/../../cache/images/demo.png';
$fontPath = __DIR__.'/../../fonts/msyh.ttf';
$engine = new ImagickEngine([]);
$outH = $engine->modifyPdf($pdfPath, $data);
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

# 实测效果，上面向单张图片9个方位 添加了文字，并保存为图片耗时如下
# ./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testMoreText'
# Runtime:       PHP 8.3.19
# Time: 00:00.336, Memory: 10.00 MB
```
### 图片合成 gif 动图
```
## co 模式下 5张图片合成gif动图，带旋转转场效果,实测耗时与 fpm 在 toPath() 生成文件耗时无差异，在生成二进制流时在 co 模式下效果更好，5张图带转场效果 co 模式下快 6 秒
# Time: 00:20.911, Memory: 17.91 MB
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testCoImagesToGif'


# Time: 00:26.889, Memory: 10.00 MB
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testImagesToGif'

```
### pdf 指定页数打点或者合成图片，5页 pdf 在每页做图片和文字合成
```
# fpm 模式下效率大增
# Time: 00:28.361, Memory: 10.00 MB
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testModifyPdf'


# co 模式下效率大增
# Time: 00:05.969, Memory: 10.00 M
./vendor/bin/phpunit -c phpunit.xml --filter 'HyperfTest\\Cases\\ExampleTest::testCoModifyPdf'

```