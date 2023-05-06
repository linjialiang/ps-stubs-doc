<?php
// +----------------------------------------------------------------------
// | php官方手册处理类
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
use DOMElement;
use DOMException;

class PhpDoc
{
    /**
     * PHP文档外链
     * 也可设为本地链接 例: file:///D:/temp/php-chunked-xhtml/
     */
    const PHP_URL = 'https://www.php.net/manual/zh/';

    /**
     * php文档手册目录
     */
    const PHP_PATH = __DIR__ . '/../raw/php-chunked-xhtml/';

    /**
     * 临时文件存放目录
     */
    const TEMP_PATH = __DIR__ . '/../raw/temp/';

    /**
     * @var DOMDocument DOM对象
     */
    private DOMDocument $dom;

    /**
     * @var DOMElement|null DOM元素
     */
    private DOMElement|null $element;

    public function __construct()
    {
        $this->dom = new DOMDocument(encoding: 'utf-8');
    }

    /**
     * 处理文档
     * @return void
     * @throws DOMException
     */
    public function run(): void
    {
        if (!is_dir(self::TEMP_PATH)) mkdir(self::TEMP_PATH);
        // 开打php中文手册目录句柄
        if (!($handle = @opendir(self::PHP_PATH))) exit('目录打开失败');
        while (false !== ($fileName = readdir($handle))) {
            $filePath = self::PHP_PATH . $fileName;
            if (!is_file($filePath) || !str_ends_with($fileName, '.html')) continue;
            // html文件载入DOM对象
            if (!@$this->dom->loadHTMLFile($filePath)) continue;
            // 获取所需元素 DOMElement
            $this->element = $this->dom->getElementById(substr($fileName, 0, strlen($fileName) - 5));
            if (empty($this->element)) continue;
            // 修改节点下链接
            $this->modifyUrl();
            // 处理样式
            $this->handleStyle();
            // 重设代码颜色以便在黑色主题下查看
            $html = $this->dom->saveHTML($this->element);
            $html = preg_replace('/ *' . PHP_EOL . ' */', '', $html); // 内容转成1行
            $html = str_replace('#0000BB', '#9876AA', $html);
            $html = str_replace('/*', '//', $html); // */ 不转义会导致phpstorm文档报错
            $html = str_replace('*/', '', $html);   // */ 不转义会导致phpstorm文档报错
            // 文件保存到TEMP目录
            $this->save_file(self::TEMP_PATH . $fileName, $html);
        }
        closedir($handle);
    }

    /**
     * 修改链接
     * @return void
     */
    private function modifyUrl(): void
    {
        $links = $this->element->getElementsByTagName('a'); // DOMNodeList
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
                $link->parentNode->replaceChild($this->dom->createTextNode("{@link $link->textContent}"), $link);
            } else {
                // 如果未匹配到任何类型, 改成官网外链(网站外链为php/本地为html)
                $link->setAttribute('href', self::PHP_URL . str_replace('.html', '.php', $url));
            }
        }
    }

    /**
     * 处理样式
     * @return void
     * @throws DOMException
     */
    private function handleStyle(): void
    {
        $tags = $this->element->getElementsByTagName('*');
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
                $preChild = $this->dom->createElement('span');
                $textList = explode("\n", $tag->textContent);
                $tag->textContent = "";
                foreach ($textList as $text) {
                    if (!empty($text)) {
                        $childText = $this->dom->createTextNode($text);
                        $preChild->appendChild($childText);
                        $childElement = $this->dom->createElement('br');
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
    private function save_file(string $filePath, string $content, bool $isAppend = false): void
    {
        $handle = fopen($filePath, $isAppend ? 'a+' : 'w+');
        fwrite($handle, $content);
        fclose($handle);
    }
}
