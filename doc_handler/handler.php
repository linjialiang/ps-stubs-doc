<?php

/**
 * 处理 HTML
 */

include __DIR__ . '/lib/SimpleHtmlDom.php';

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
 * 处理后的文件路径
 */
const TEMP_PATH = __DIR__ . '/../raw/temp/';


function myPrint(...$args): void
{
    foreach ($args as $arg) {
        print_r($arg);
        echo PHP_EOL;
    }
}

/**
 * 修改衔接
 * @param SimpleHtmlDom $dom
 */
function modifyUrl(SimpleHtmlDom $dom): void
{
    $links = $dom->find('a');
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

function modifyAttr($dom, $selector, $value, $attr = 'style'): void
{
    $subs = $dom->find($selector);
    foreach ($subs as $sub) {
        $sub->setAttribute($attr, $value);
    }
}

function modifyTag($dom, $selector, $outside, $pre, $after, $one = false): void
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

function modifyOutput($dom): void
{
    $subs = $dom->find("pre");
    foreach ($subs as $sub) {
        $text = $sub->innertext();
        if (str_starts_with($text, "\n")) {
            $text = substr($text, 1);
        }
        $text = '<span>' . str_replace("\n", "<br>", $text) . '</span>';
        $sub->outertext = $text;
    }
}

/**
 * 修改文本
 */
function modifyStr($html): array|string
{
    // 防止注释异常终止
    $html = str_replace('/*', '//', $html);
    $html = str_replace('*/', '', $html);
    // 重设代码颜色以便在黑色主题下查看
    $html = str_replace('#0000BB', '#9876AA', $html);
    // 清理换行
    $html = str_replace("\r", '', $html);
    return str_replace("\n", '', $html);
}

/**
 * 第7步 处理常量
 * @param string $file
 * @return void
 */
function handleConst(string $file = 'filesystem.consts.html'): void
{
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

/**
 * 第6步
 * @param $dom
 * @return mixed
 */
function handleStyle($dom): mixed
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
    modifyTag($dom, "div[class='refsect1']", true, '<br></br><div style="BORDER-TOP: gray 1px dashed; OVERFLOW: hidden; HEIGHT: 1px"></div>', '');
    // modifyTag($dom, "div[class='refsect1']", true, '<br></br><hr></hr>', '');
    return $dom;
}

/**
 * 第5步 从字符串中获取 html dom
 * @param $str
 * @param true $lowercase
 * @param true $forceTagsClosed
 * @param string $target_charset
 * @param bool $stripRN
 * @param string $defaultBRText
 * @param string $defaultSpanText
 * @return SimpleHtmlDom|false
 */
function str_get_html($str, true $lowercase = true, true $forceTagsClosed = true, string $target_charset = DEFAULT_TARGET_CHARSET, bool $stripRN = true, string $defaultBRText = DEFAULT_BR_TEXT, string $defaultSpanText = DEFAULT_SPAN_TEXT): SimpleHtmlDom|false
{
    $dom = new SimpleHtmlDom(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
    if (empty($str) || strlen($str) > MAX_FILE_SIZE) {
        $dom->clear();
        return false;
    }
    $dom->load($str, $lowercase, $stripRN);
    return $dom;
}

/**
 * 第4步 加载文本内容
 * @param $name
 * @return bool|string
 */
function loadStr($name): bool|string
{
    $path = IN_PATH . $name;
    $content = file_get_contents($path) or die("Unable to open file!");
    return $content;
}

/**
 * 第三步
 * 处理函数
 */
function handle($file = 'function.date.html'): void
{
    $content = loadStr($file);
    $selector = str_get_html($content, true, true, DEFAULT_TARGET_CHARSET, false);
    $name = substr($file, 0, strlen($file) - 5);
    $dom = $selector->find("div[id='$name']", 0);
    modifyUrl($dom);
    handleStyle($dom);
    $html = $dom->outertext;
    $html = modifyStr($html);
    file_put_contents(TEMP_PATH . '/' . $file, $html);
    echo $file . LINE;
}


/**
 * 第二步
 * 处理类
 * @return array
 */
function getClass(): array
{
    $class = [];
    if (@$handle = opendir(IN_PATH)) {
        while (($file = readdir($handle)) !== false) {
            $pre = 'class.';
            if ((str_starts_with($file, $pre))) {
                $clsName = substr($file, strlen($pre), strlen($file) - strlen($pre) - 5);
                $class[$clsName] = 1;
            }
        }
    }
    return $class;
}

// 第一步
function handleAll(): void
{
    if (!is_dir(TEMP_PATH)) mkdir(TEMP_PATH);
    $class = getClass();
    $class['function'] = 1;
    $class['class'] = 1;
    $class['reserved'] = 1;
    if (@$handle = opendir(IN_PATH)) {
        while (($file = readdir($handle)) !== false) {
            $tokens = explode('.', $file);
            $prefix = $tokens[0];
            if (@$class[$prefix]) {
                handle($file);
            }
            if ($tokens[count($tokens) - 2] == 'constants') {  // 收集常量
                handleConst($file);
            }
        }
    }
}

handleAll(); // 执行