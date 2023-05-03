<?php

/**
 * PHP文档外链
 * 也可设为本地链接 例: file:///D:/temp/php-chunked-xhtml/
 */
const DOC_URL = 'https://www.php.net/manual/zh/';

/**
 * 文档手册
 */
const IN_PATH = __DIR__ . '/../raw/php-chunked-xhtml/';

/**
 * 临时文件存放目录
 */
const TEMP_PATH = __DIR__ . '/../raw/temp/';

/**
 * 日志路径
 */
const LOG_PATH = __DIR__ . '/../raw/log/';

/**
 * 指定换行符
 */
const LINE_BREAK = "\n";

/**
 * 处理全部
 * @return void
 */
function run(): void
{
    if (!is_dir(TEMP_PATH)) mkdir(TEMP_PATH);
    if (!is_dir(LOG_PATH)) mkdir(LOG_PATH);
    $handle = @opendir(IN_PATH); // 开打手册目录句柄
    if (!$handle) exit('目录打开失败');
    $typeList = ['function', 'class', 'reserved']; // 函数、类、保留字文件
    while (false !== ($fileName = readdir($handle))) {
        if (!is_file(IN_PATH . $fileName)) continue;
        $tokens = explode('.', $fileName);
        $filePath = IN_PATH . $fileName;
        // 处理函数、类、保留字
        if (!in_array($tokens[0], $typeList)) continue;
        $dom = new DOMDocument();
        @$dom->loadHTMLFile($filePath); // html文件载入DOM对象
        $node = $dom->getElementById(substr($fileName, 0, strlen($fileName) - 5)); // 获取所需节点
        if (empty($node)) continue;
        // 修改节点下链接
        modifyUrl($node, $dom);
        // 处理样式
        handleStyle($node, $dom);
        // 重设代码颜色以便在黑色主题下查看
        $html = $dom->saveHTML($node);
        $html = preg_replace('/ *' . LINE_BREAK . ' */', '', $html); // 内容转成1行
        $html = str_replace('#0000BB', '#9876AA', $html);
        $html = str_replace('*/', '*\/', $html); // */ 不转义会导致phpstorm文档报错
        $classFile = TEMP_PATH . $fileName;
        save_file(LOG_PATH . 'class.log', "$filePath" . LINE_BREAK, true);
        save_file($classFile, $html); // 文件保存到临时目录
    }
    closedir($handle);
}

/**
 * 修改链接
 * @param $node
 * @param $dom
 */
function modifyUrl($node, $dom): void
{
    $links = $node->getElementsByTagName('a');
    foreach ($links as $link) {
        // 不处理外链
        $href = $link->getAttribute('href');
        if (str_contains($href, 'http://')) continue;
        if (str_contains($href, 'https://')) continue;
        // 已知类型, 方法,类静态方法..
        $className = $link->getAttribute('class');
        if ($className === 'function' || $className === 'methodname') {
            // 创建一个新的文本节点
            $text = "{@link $link->textContent}"; // 拿到文本内容，并修改成 phpstorm 的连接
            $childText = $dom->createTextNode($text);
            // 拿到父节点
            $parent = $link->parentNode;
            // 替换子节点
            $parent->replaceChild($childText, $link);
            // // 在子节点之前插入
            // $parent->insertBefore($childText, $link);
            // // 移除a节点
            // $parent->removeChild($link);
            // unset($parent);
            // unset($childText);
        } else {
            // 如果未匹配到任何类型, 改成官网外链
            // 网站外链为php 本地为html
            $link->setAttribute('href', DOC_URL . str_replace('.html', '.php', $href));
        }
    }
}

/**
 * 处理样式
 * @param $node
 * @param $dom
 * @return void
 */
function handleStyle($node, $dom): void
{
    $tags = $node->getElementsByTagName('*');
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

run();
