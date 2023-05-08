<?php
// +----------------------------------------------------------------------
// | php-doc [ PHP is the best language for web programming ]
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-05-08 09:45:00
// +----------------------------------------------------------------------
$p = PHP_FLOAT_MAX;

/**
 * <span class="simpara"> 寻找可动态加载扩展的默认目录（除非被 <a href="ini.core.php#ini.extension-dir" class="link">extension_dir</a>覆盖 ）。默认为 <strong><code>PHP_PREFIX</code></strong> （在 Windows 上是 <code class="code">PHP_PREFIX . "\\ext"</code>）。</span>{@link function_exists()}
 */
define('PHP_FLOAT_MAX', 1.7976931348623e+308);

class Demo{
    function abc()
    {
        echo __LINE__;
    }
}