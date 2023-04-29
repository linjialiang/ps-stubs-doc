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

function handleAll()
{
    if (!is_dir(TEMP_PATH)) mkdir(TEMP_PATH);
    if (!is_dir(LOG_PATH)) mkdir(LOG_PATH);
    $manHandle = @opendir(IN_PATH); // 开打手册目录句柄
    if (!$manHandle) exit('目录打开失败');
    $typeList = ['function', 'class', 'reserved']; // 函数、类、保留字文件
    while (false !== ($fileName = readdir($manHandle))) {
        if (is_file(IN_PATH . $fileName)) {
            $tokens = explode('.', $fileName);
            $filePath = IN_PATH . $fileName;
            // 处理函数、类、保留字
            if (in_array($tokens[0], $typeList)) {
                $dom = new DOMDocument();
                @$dom->loadHTMLFile($filePath); // html文件载入DOM对象
                $node = $dom->getElementById(substr($fileName, 0, strlen($fileName) - 5)); // 获取所需节点
                $content = preg_replace('/ *\n */', '', $dom->saveHTML($node)); // 内容转成1行
                $classFile = TEMP_PATH . "$fileName";
                save_file(LOG_PATH . 'class.log', "$filePath\n", true);
                save_file($classFile, $content); // 文件保存到临时目录
            }
            // 收集常量
            if ($tokens[count($tokens) - 2] == 'constants') {
                $dom = new DOMDocument();
                @$dom->loadHTMLFile($filePath); // html文件载入DOM对象
                $nodeList = $dom->getElementsByTagName('strong');
                foreach ($nodeList as $node) {
                    if ($node->firstChild->nodeName == 'code') {
                        $codeNode = $node->firstChild;
                        $constName = $codeNode->textContent;
                        $constFile = TEMP_PATH . "constant.$constName.html";
                        $nextNode = $node->parentNode->nextSibling;
                        $html = $dom->saveHTML($nextNode);
                        if (empty($html) || empty(trim($html)) || strpos($constFile, '::')) continue;
                        save_file(LOG_PATH . 'const.log', "$constFile\n", true);
                        save_file($constFile, $html);
                    }
                }
            }
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

/**
 * 修改链接
 * @param $node
 */
function modifyUrl($node): void
{
    $links = $node->find('a');
    foreach ($links as $iValue) {
        $a = $iValue;
        $href = $a->href;
        if (str_contains($href, 'http://')) {    // 不处理外链
            continue;
        }
        $known = 0;
        if (str_contains($href, 'function.')) {
            $known = 1;
        } else if (str_contains($a->innertext, '::')) {
            $known = 1;
        }
        if ($known) {   // 已知类型, 方法,类静态方法..
            $href = '{@link ' . $a->innertext . '}';
            $a->outertext = $href;
        } else {        // 如果未匹配到任何类型, 改成官网外链
            $href = str_replace('.html', '.php', $href);    // 网站外链为php 本地为html
            $a->href = DOC_URL . $href;
        }
    }
}

handleAll();