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

use src\DOM;

/**
 * PHP文档外链
 * 也可设为本地链接 例: file:///D:/temp/php-chunked-xhtml/
 */
const PHP_URL = 'https://www.php.net/manual/zh/';

/**
 * php中文文档手册
 */
const PHP_PATH = __DIR__ . '/raw/php-chunked-xhtml/';

/**
 * 临时文件存放目录
 */
const TEMP_PATH = __DIR__ . '/raw/temp/';

require __DIR__ . '/src/DOM.php';

run();

/**
 * 处理文档
 * @return void
 */
function run(): void
{
    if (!is_dir(TEMP_PATH)) mkdir(TEMP_PATH);
    // 开打php中文手册目录句柄
    if (!($handle = @opendir(PHP_PATH))) exit('目录打开失败');
    // $typeList = ['function', 'class', 'reserved']; // 函数、类、保留字文件
    while (false !== ($fileName = readdir($handle)) && str_ends_with($fileName, '.html')) {
        $filePath = PHP_PATH . $fileName;
        if (!is_file($filePath)) continue;
        // TODO 测试执行 class.mysqli 和 function.array 开头的文件
        // if (!str_contains($fileName, 'class.mysqli') && !str_contains($fileName, 'function.array')) continue;
        // $tokens = explode('.', $fileName);
        // 处理函数、类、保留字
        // if (in_array($tokens[0], $typeList)) {
        // html文件载入DOM对象
        $dom = new DOM();
        if (!@$dom->loadHTMLFile($filePath)) continue;
        $element = $dom->getElementById(substr($fileName, 0, strlen($fileName) - 5)); // 获取所需元素 DOMElement
        if (empty($element)) continue;
        // 修改节点下链接
        modifyUrl($element, $dom);
        // 处理样式
        handleStyle($element, $dom);
        // 重设代码颜色以便在黑色主题下查看
        $html = $dom->saveHTML($element);
        $html = preg_replace('/ *' . PHP_EOL . ' */', '', $html); // 内容转成1行
        $html = str_replace('#0000BB', '#9876AA', $html);
        $html = str_replace('/*', '//', $html); // */ 不转义会导致phpstorm文档报错
        $html = str_replace('*/', '', $html); // */ 不转义会导致phpstorm文档报错
        $classFile = TEMP_PATH . $fileName;
        save_file($classFile, $html); // 文件保存到临时目录
        // }
        // 处理常量 先忽略
    }
    closedir($handle);
}

/**
 * 修改链接
 * @param $element
 * @param $dom
 */
function modifyUrl($element, $dom): void
{
    $links = $element->getElementsByTagName('a'); // DOMNodeList
    $linkCount = $links->count();
    // 由于循环处理时，a元素会被文本节点覆盖，数量减少，只能从最大的开始处理，才能保证每个都执行到
    for ($i = $linkCount - 1; $i >= 0; $i--) {
        $link = $links->item($i);
        $url = $link->getAttribute('href');
        // 不处理外链
        if (str_contains($url, 'http://') || str_contains($url, 'https://')) continue;
        // 已知类型, 方法,类静态方法..
        $className = $link->getAttribute('class');
        if ($className === 'function' || $className === 'methodname' || strpos($link->textContent, '::')) {
            // 替换子节点
            // 创建一个新的文本节点，拿到$link的文本内容，并修改成 phpstorm 的方法链接
            $link->parentNode->replaceChild($dom->createTextNode("{@link $link->textContent}"), $link);
        } else {
            // 如果未匹配到任何类型, 改成官网外链(网站外链为php/本地为html)
            $link->setAttribute('href', PHP_URL . str_replace('.html', '.php', $url));
        }
    }
}

/**
 * 处理样式
 * @param $element
 * @param $dom
 */
function handleStyle($element, $dom): void
{
    $tags = $element->getElementsByTagName('*');
    // 修改样式
    foreach ($tags as $tag) {
        $className = $tag->getAttribute('class');
        // 参数标签, 9070A1 编辑器紫, EE82EE 鲜艳紫, 00B5FF 鲜艳蓝,4285F4 一般蓝, 19A1FA 3A95FF ok蓝
        $styleMap = [
            'methodname' => 'color:#CC7832',                               // 方法颜色
            'function' => 'color:#CC7832',                                 // 方法颜色
            'type' => 'color:#EAB766',                                     // 类型颜色
            'parameter' => 'color:#3A95FF',                                // 参数颜色
            'note' => 'border:1px gray solid',                             // note
            'phpcode' => 'border-color:gray;background:#232525',           // php代码
            'example-contents screen' => 'color:AFB1B3;background:black;padding-left:5px;', // output
        ];
        if (isset($styleMap[$className])) $tag->setAttribute('style', $styleMap[$className]);
        // pre 增加<span>子标签，将换行改成<br>
        if ($tag->nodeName == 'pre' && !$tag->hasAttribute('class')) {
            $preChild = $dom->createElement('span');
            $textList = explode("\n", $tag->textContent);
            $tag->textContent = "";
            foreach ($textList as $text) {
                if (!empty($text)) {
                    $childText = $dom->createTextNode($text);
                    $preChild->appendChild($childText);
                    $childElement = $dom->createElement('br');
                    $preChild->appendChild($childElement);
                }
            }
            $tag->appendChild($preChild);
        }
    }
}

/**
 * 保存文件
 * @param string $filePath
 * @param string $content
 * @param bool $isAppend 是否追加写入 默认false
 * @return void
 */
function save_file(string $filePath, string $content, bool $isAppend = false): void
{
    $handle = fopen($filePath, $isAppend ? 'a+' : 'w+');
    fwrite($handle, $content);
    fclose($handle);
}