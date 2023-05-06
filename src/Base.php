<?php
// +----------------------------------------------------------------------
// | php手册转phpstorm-stubs 基类
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-05-03 14:04:35
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace core;

class Base
{
    /**
     * 保存文件
     * @param string $filePath
     * @param string $content
     * @param bool $isAppend 是否追加写入 默认false
     * @return void
     */
    protected function save_file(string $filePath, string $content, bool $isAppend = false): void
    {
        $handle = fopen($filePath, $isAppend ? 'a+' : 'w+');
        fwrite($handle, $content);
        fclose($handle);
    }
}