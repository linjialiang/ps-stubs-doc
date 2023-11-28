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
use DOMNodeList;

class PhpDoc
{
    /**
     * PHP文档外链
     * 也可设为本地链接 例: file:///D:/temp/php-chunked-xhtml/
     */
    private const string PHP_URL = 'https://www.php.net/manual/zh/';

    /**
     * php文档手册目录
     */
    private const string PHP_PATH = __DIR__ . '/../raw/php-chunked-xhtml/';

    /**
     * 文件临时存放目录
     */
    private const string TEMP_PATH = __DIR__ . '/../raw/temp/';

    /**
     * 收集常量临时存放目录
     */
    private const string CONST_TEMP_PATH = __DIR__ . '/../raw/const_temp/';

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
        if (!is_dir(self::CONST_TEMP_PATH)) mkdir(self::CONST_TEMP_PATH);
        // 开打php中文手册目录句柄
        if (!($handle = @opendir(self::PHP_PATH))) exit('目录打开失败');
        while (false !== ($fileName = readdir($handle))) {
            $filePath = self::PHP_PATH . $fileName;
            if (!is_file($filePath) || !str_ends_with($fileName, '.html')) continue;
            // html文件载入DOM对象
            if (!@$this->dom->loadHTMLFile($filePath)) continue;
            if (!!strpos($fileName, '.constants.')) {// 收集常量
                $codeList = $this->dom->getElementsByTagName('code'); // DOMNodeList
                if ($codeList->count() === 0) continue;
                foreach ($codeList as $code) {// DOMElement
                    $newFileName = $code->textContent;
                    if (
                        $code->parentNode->tagName !== 'strong' ||
                        !preg_match('/^[0-9A-Z_]+$/', $newFileName) ||
                        !str_starts_with(trim($code->parentNode->parentNode->textContent), $newFileName) ||
                        !in_array($code->parentNode->parentNode->tagName, ['dd', 'dt', 'td'])
                    ) continue;
                    $tdList = ['errorfunc.constants.html', 'pcre.constants.html', 'language.constants.magic.html'];
                    if (!in_array($fileName, $tdList) && $code->parentNode->parentNode->tagName === 'td') continue;
                    // 获取所需元素 DOMElement
                    $this->element = $code->parentNode->parentNode->nextElementSibling;
                    if (empty($this->element) || empty(trim($this->element->textContent))) continue;
                    $this->handleElement(self::CONST_TEMP_PATH . strtolower($newFileName) . '.html', true);
                }
            }
            // 获取所需元素 DOMElement
            $this->element = $this->dom->getElementById(substr($fileName, 0, strlen($fileName) - 5));
            $this->handleElement(self::TEMP_PATH . $fileName);
        }
        closedir($handle);
    }

    /**
     * 处理元素
     * @param string $savePath
     * @param bool $isConst 是否常量
     * @return void
     * @throws DOMException
     */
    private function handleElement(string $savePath, bool $isConst = false): void
    {
        if (empty($this->element)) return;
        // 修改节点下链接
        $links = $this->element->getElementsByTagName('a'); // DOMNodeList
        $this->modifyUrl($links);
        // 处理样式
        $tags = $this->element->getElementsByTagName('*'); // DOMNodeList
        $this->handleStyle($tags);
        $html = $this->dom->saveHTML($this->element);
        // 重设代码颜色以便在黑色主题下查看
        $html = preg_replace('/ *' . PHP_EOL . ' */', '', $html); // 内容转成1行
        $html = str_replace('#0000BB', '#9876AA', $html);
        $html = str_replace('/*', '//', $html); // */ 不转义会导致phpstorm文档报错
        $html = str_replace('*/', '', $html); // */ 不转义会导致phpstorm文档报错
        // 移除首个标签
        if ($isConst) $html = preg_replace(['/^<(td|dd|dt)>/', '/<\/(td|dd|dt)>$/'], '', $html);
        // 将 code 和 pre 修改成 code1 和 pre1
        $html = preg_replace(
            ['#<code>#', '#<code #', '#</code>#', '#<pre>#', '#<pre #', '#</pre>#'],
            ['<code1>', '<code1 ', '</code1>', '<pre1>', '<pre1 ', '</pre1>'],
            $html
        );
        // 文件保存到指定目录
        $this->save_file($savePath, $html);
    }

    /**
     * 修改链接
     * @param DOMNodeList $links
     * @return void
     */
    private function modifyUrl(DOMNodeList $links): void
    {
        $linkCount = $links->count();
        // 由于循环处理时，a元素会被文本节点覆盖，数量减少，只能从最大的开始处理，才能保证每个都执行到
        for ($i = $linkCount - 1; $i >= 0; $i--) {
            $link = $links->item($i);
            $url = $link->getAttribute('href');
            // 不处理外链
            if (str_contains($url, 'http://') || str_contains($url, 'https://')) continue;
            // 已知类型, 方法,类静态方法..
            $className = $link->getAttribute('class');
            if ($className === 'function' || $className === 'methodname' || !!strpos($link->textContent, '::')) {
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
     * @param DOMNodeList $tags
     * @return void
     * @throws DOMException
     */
    private function handleStyle(DOMNodeList $tags): void
    {
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
