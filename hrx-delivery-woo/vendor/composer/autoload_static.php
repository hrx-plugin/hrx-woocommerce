<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite4497b6c884deb0f060be61182076ac8
{
    public static $prefixLengthsPsr4 = array (
        's' => 
        array (
            'setasign\\Fpdi\\' => 14,
        ),
        'H' => 
        array (
            'HrxDeliveryWoo\\' => 15,
            'HrxApi\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'setasign\\Fpdi\\' => 
        array (
            0 => __DIR__ . '/..' . '/setasign/fpdi/src',
        ),
        'HrxDeliveryWoo\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
        'HrxApi\\' => 
        array (
            0 => __DIR__ . '/..' . '/hrx/api-lib/src',
        ),
    );

    public static $classMap = array (
        'FPDF' => __DIR__ . '/..' . '/setasign/fpdf/fpdf.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite4497b6c884deb0f060be61182076ac8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite4497b6c884deb0f060be61182076ac8::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite4497b6c884deb0f060be61182076ac8::$classMap;

        }, null, ClassLoader::class);
    }
}
