<?php
// +----------------------------------------------------------------------
// | 处理php官方手册
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-05-03 14:04:35
// +----------------------------------------------------------------------
declare (strict_types=1);

use src\PhpDoc;

/**
 * 引入 PhpDoc 类
 */
require __DIR__ . '/src/PhpDoc.php';

$handle = new PhpDoc();
try {
    $handle->run();
} catch (DOMException $e) {
    echo $e->getMessage() . PHP_EOL;
}

