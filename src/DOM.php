<?php
// +----------------------------------------------------------------------
// | php-doc [ PHP is the best language for web programming ]
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-05-03 14:04:35
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace src;
use DOMDocument;
use PharData;

class DOM extends DOMDocument
{
    public function __construct(string $version = '1.0', string $encoding = 'utf-8')
    {
        parent::__construct($version, $encoding);
    }
}