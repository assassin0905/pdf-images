<?php
use function Hyperf\Support\env;
use Wudg\PdfImages\Provider\ImagickProvider;
return [
    'default' => env('PDF_ENGINE', 'imagick'),
    'save_img_path' => BASE_PATH.'/storage/images/'.date("y/md"), //生成的图片保存路径,相对根目录
    'save_pdf_path' => BASE_PATH.'/storage/pdf/'.date("y/md"), //生成的pdf保存路径,相对根目录
    'engine' => [
        'imagick' => [
            'driver' => ImagickProvider::class,
            'dpi' => 300, //渲染 DPI,越高细节越好，内存占用也越高
            'width' => 1191, //默认生成图片宽度，宽度保持统一高度自适应
            'compression_quality' => 100, //压缩质量，0-100，越高质量越好
            'ext' => 'jpeg', //pdf 生成的图片后缀名称，支持 png,jpeg
        ]
    ]

];
