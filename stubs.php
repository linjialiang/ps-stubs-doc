<?php
// +----------------------------------------------------------------------
// | 处理phpstorm-stubs
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-05-03 14:04:35
// +----------------------------------------------------------------------
declare (strict_types=1);

use src\PsStubs;

/**
 * 引入 DOM 类
 */
require __DIR__ . '/src/PsStubs.php';

$handle = new PsStubs();
try {
    $handle->run();
} catch (DOMException $e) {
    echo $e->getMessage() . PHP_EOL;
}

