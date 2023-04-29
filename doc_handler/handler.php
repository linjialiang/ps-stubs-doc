<?php

const LINE = "\n";

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
    while (false !== ($file = readdir($manHandle))) {
        if (is_file(IN_PATH . $file)) {
            $tokens = explode('.', $file);
            // 处理函数、类、保留字
            $filePath = IN_PATH . $file;
            if (in_array($tokens[0], $typeList)) {
                save_file(LOG_PATH . 'class.log', "$filePath\n", true);
                $dom = new DOMDocument();
                @$dom->loadHTMLFile($filePath); // html文件载入DOM对象
                $node = $dom->getElementById(substr($file, 0, strlen($file) - 5)); // 获取所需节点
                $content = preg_replace('/ *\n */', '', $dom->saveHTML($node)); // 内容转成1行
                save_file($filePath, $content); // 文件保存到临时目录
            };
            // 收集常量
            if ($tokens[count($tokens) - 2] == 'constants') {
                save_file(LOG_PATH . 'constants.log', "$filePath\n", true);
                $dom = new DOMDocument();
                @$dom->loadHTMLFile($filePath); // html文件载入DOM对象
                $nodeList = $dom->getElementsByTagName('strong');
                foreach ($nodeList as $node) {
                    if ($node->firstChild->nodeName == 'code') {
                        $codeNode = $node->firstChild;
                        $constName = $codeNode->textContent;
                        $outFile = "constant.$constName.html";
                        $next = $node->parentNode()->nextSibling();
                        var_dump($next);
                        // modifyUrl($next);
                        // $html = $next->innertext;
                        // if (!$html) continue;
                        // if (trim($html) == '') continue;
                        // if (strpos($outFile, '::')) continue;
                        // save_file(TEMP_PATH . $outFile, $html);
                    }
                }
                die;
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
function save_file(string $filePath, string $content, bool $isAppend = false)
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
        if (strstr($href, 'http://')) {    // 不处理外链
            continue;
        }
        $known = 0;
        if (strstr($href, 'function.')) {
            $known = 1;
        } else if (strstr($a->innertext, '::')) {
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