<?php

/**
 * 处理 HTML
 */

include './simple_html_dom.php';

const LINE = "\n";

/**
 * PHP文档外链
 * 也可设为本地衔接 例: file:///D:/Temp/php-chunked-xhtml/
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


function myPrint(...$args)
{
    foreach ($args as $arg) {
        print_r($arg);
        echo PHP_EOL;
    }
}

/**
 * 加载文本内容
 * @param $name
 * @return bool|string
 */
function loadStr($name)
{
    $content = file_get_contents(IN_PATH . $name) or die("Unable to open file!");
    return $content;
}

/**
 * 修改衔接
 * @param simple_html_dom $dom
 */
function modifyUrl($dom)
{
    $links = $dom->find('a');
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

/**
 * 修改文本
 */
function modifyStr($html)
{
    // 防止注释异常终止
    $html = str_replace('/*', '//', $html);
    $html = str_replace('*/', '', $html);
    // 重设代码颜色以便在黑色主题下查看
    $html = str_replace('#0000BB', '#9876AA', $html);
    // 清理换行
    $html = str_replace("\r", '', $html);
    $html = str_replace("\n", '', $html);
    return $html;
}

function modifyAttr($dom, $selector, $value, $attr = 'style')
{
    $subs = $dom->find($selector);
    foreach ($subs as $sub) {
        $sub->setAttribute($attr, $value);
    }
}

function modifyTag($dom, $selector, $outside, $pre, $after, $one = false)
{
    $subs = $dom->find($selector);
    foreach ($subs as $sub) {
        if ($outside) {
            $sub->outertext = $pre . $sub->outertext() . $after;
        } else {
            $sub->outertext = $pre . $sub->innertext() . $after;
        }
        if ($one) {
            break;
        }
    }
}

function modifyOutput($dom)
{
    $subs = $dom->find("pre");
    foreach ($subs as $sub) {
        $text = $sub->innertext();
        if (substr($text, 0, 1) == "\n") {
            $text = substr($text, 1);
        }
        $text = '<span>' . str_replace("\n", "<br>", $text) . '</span>';
        $sub->outertext = $text;
    }
}

function handleStyle($dom)
{
    // 方法颜色
    modifyAttr($dom, '.methodname', 'color:#CC7832');
    modifyAttr($dom, '.function strong', 'color:#CC7832');
    // 类型颜色
    modifyAttr($dom, '.type', 'color:#EAB766');
    // 参数颜色
    modifyAttr($dom, '.parameter', 'color:#9070A1');
    // 方法描述背景，源码隐藏
    // modifyAttr($dom, '.methodsynopsis', 'border:1px gray;padding-left:5px;background:#232525');
    // 添加分隔符，源码隐藏
    // modifyAttr($dom, "div[class='refsect1']", "BORDER-TOP: gray 1px dashed; OVERFLOW: hidden; HEIGHT: 1px");
    // note
    modifyAttr($dom, ".note", "border:1px gray solid");
    // php代码
    modifyAttr($dom, ".phpcode", "border-color:gray;background:#232525");
    // output
    modifyAttr($dom, ".screen", "color:AFB1B3;background:black;padding-left:5px;");
    // pre
    modifyOutput($dom);
    // 源码隐藏
    // modifyTag($dom, "pre", false, '<span>', '</span>');
    // code
    modifyTag($dom, "code", false, '<span>', '</span>');
    // 参数标签, 9070A1 编辑器紫, EE82EE 鲜艳紫, 00B5FF 鲜艳蓝,4285F4 一般蓝, 19A1FA 3A95FF ok蓝
    modifyTag($dom, '.parameter', false, '<span class="parameter" style="color:#3A95FF">', '</span>');
    // 去除换行:参数,示例
    modifyTag($dom, ".parameters .para", false, '<span>', '</span>', true);
    modifyTag($dom, ".examples .para", false, '<span>', '</span>', true);
    modifyTag($dom, ".seealso .para", false, '<span>', '</span>', true);
    modifyTag($dom, ".changelog .para", false, '<span>', '</span>', true);
    // 添加分隔符,换行标签
    modifyTag($dom, "div[class='refsect1']", true, '<br><div style="BORDER-TOP: gray 1px dashed; OVERFLOW: hidden; HEIGHT: 1px"></div>', '');
    // modifyTag($dom, "div[class='refsect1']", true, '<br></br><hr></hr>', '');
    return $dom;
}

function handleAll()
{
    $file = 'class.addressinfo.html';
    $dom = load_dom($file);
    // 获取所需节点
    $node = $dom->getElementById(substr($file, 0, strlen($file) - 5));
    $content = preg_replace('/ *\n */', '', $dom->saveHTML($node));
    save_file($file, $content);

    if (!is_dir(TEMP_PATH)) mkdir(TEMP_PATH);
    $manHandle = @opendir(IN_PATH); // 开打手册目录句柄
    if (!$manHandle) exit('目录打开失败');
    $typeList = ['function', 'class', 'reserved']; // 函数、类、保留字文件
    while (false !== ($file = readdir($manHandle))) {
        if (is_file(IN_PATH . $file)) {
            $tokens = explode('.', $file);
            // 处理函数、类、保留字
            if (in_array($tokens[0], $typeList)) {
                $dom = load_dom($file);
                // 获取所需节点
                $dom->getElementById(substr($file, 0, strlen($file) - 5));
                $html = $dom->saveHTML();
                var_dump($html);
                die;
                modifyUrl($dom);
                handleStyle($dom);
                $html = $dom->outertext;
                $html = modifyStr($html);
                file_put_contents(TEMP_PATH . '/' . $file, $html);
                echo $file . LINE;
            };
            // 收集常量
            if ($tokens[count($tokens) - 2] == 'constants') {
                $content = loadStr($file);
                $selector = str_get_html($content);
                $doms = $selector->find('strong code');
                foreach ($doms as $dom) {
                    if (!$dom) continue;
                    $outFile = 'constant.' . $dom->innertext . '.html';
                    $parent = $dom->parentNode()->parentNode();
                    $next = $parent->nextSibling();
                    if (!$next) continue;
                    $next = $next->children(0);
                    if (!$next) continue;
                    modifyUrl($next);
                    $html = $next->innertext;
                    if (!$html) continue;
                    if (trim($html) == '') continue;
                    $html = modifyStr($html);
                    if (strpos($outFile, '::')) continue;
                    echo $outFile . LINE;
                    file_put_contents(TEMP_PATH . '/' . $outFile, $html);
                }
            }
        }
    }
}

/**
 * html文件载入DOM对象
 * @param string $fileName
 * @return DOMDocument
 */
function load_dom(string $fileName): DOMDocument
{
    $dom = new DOMDocument();
    $dom->loadHTMLFile(IN_PATH . $fileName);
    $dom->normalizeDocument();
    return $dom;
}

/**
 * 保存文件
 * @param string $fileName
 * @param string $content
 * @param bool $isAppend 是否追加写入 默认false
 * @return void
 */
function save_file(string $fileName, string $content, bool $isAppend = false)
{
    $handle = fopen(TEMP_PATH . $fileName, $isAppend ? 'a+' : 'w+');
    fwrite($handle, $content);
    fclose($handle);
}

handleAll();