<?php
/**
 * Created by PhpStorm.
 * User: fw
 * Date: 2018/12/21
 * Time: 20:47
 */

const DOC_IN_PATH = __DIR__ . '/../raw/temp/';
const DOC_OUT_PATH = __DIR__ . '/../raw/phpstorm-stubs-2022.3';
const LINE = "\n";

function myPrint(...$args)
{
    foreach ($args as $arg) {
        print_r($arg);
        echo PHP_EOL;
    }
}

/**
 * @param $dir
 * @param $parent
 * @param $files
 * @return array|mixed|void
 */
function my_dir($dir, $parent = '', &$files = [])
{
    myPrint($dir);
    if (@$handle = opendir($dir)) { // 注意这里要加一个@，不然会有warning错误提示：）
        while (($file = readdir($handle)) !== false) {
            if ($file != ".." && $file != ".") { // 排除根目录
                if (is_dir($dir . "/" . $file)) { // 如果是子文件夹，就进行递归
                    my_dir($dir . "/" . $file, $parent . '/' . $file, $files);
                } else { // 不然就将文件的名字存入数组
                    $files[] = $parent . '/' . $file;
                }
            }
        }
        closedir($handle);
        return $files;
    }
}

function isComment($line)
{
    foreach (['/*', '*', '*/', '#'] as $item) {
        if (strpos($line, $item) === 0) {
            return true;
        }
    }
    return false;
}

function getComment($token, $oldComment)
{
    if (strpos($token, 'constant.') !== 0)  // 不是常量替换下划线
        $token = str_replace('_', '-', $token);
    $file = DOC_IN_PATH . $token . '.html';
    if (file_exists($file)) {
        $keepLine = '';
        $keepLine2 = '';
        if ($oldComment) {
            $olds = explode("\n", $oldComment);
            foreach ($olds as $old) {
                $old2 = trim($old);
                if (
                    strpos($old2, '* @param') === 0 ||  // 保留参数行
                    strpos($old2, '* @return') === 0    // 保留return行
                ) {
                    $keepLine .= strip_tags($old) . LINE;
                } elseif (strpos($old2, '#[') === 0) {
                    $keepLine2 .= LINE . strip_tags($old);
                }
            }
        }
        if (!empty($keepLine)) {
            $keepLine = LINE . $keepLine;
        }
        $comment = file_get_contents($file);
        $comment = '/**' . LINE . ' * ' . $comment . $keepLine . ' */' . $keepLine2 . LINE;
        return $comment;
    } else {
        return $oldComment;
    }
}

function isElement($line, $type)
{
    $tokens = explode(' ', $line);
    for ($i = 0; $i < count($tokens); $i++) {
        if ($tokens[$i] == $type) {
            $name = $tokens[$i + 1];
            $name = trim($name);
            if (strpos($name, '(')) {
                $name = substr($name, 0, strpos($name, '('));
            }
            return $name;
        }
    }
    return false;
}

function isClass($line)
{
    return isElement($line, 'class');
}

function isFunction($line)
{
    return isElement($line, 'function');
}

function isConst($line)
{
    $line = str_replace(' ', '', $line);
    $pre = "define('";
    if (strpos($line, $pre) === 0) {
        $line = str_replace($pre, '', $line);
        $const = explode("'", $line)[0];
        return $const;
    }
    return false;
}

function isVar($line)
{
    $line = str_replace(' ', '', $line);
    $pre = "$";
    if (strpos($line, $pre) === 0) {
        $line = str_replace($pre, '', $line);
        $line = str_replace('_', '', $line);
        $var = explode("=", $line)[0];
        return $var;
    }
    return false;
}

function handle($name)
{
    $file = DOC_OUT_PATH . $name;
    $newContent = '';
    $handle = fopen($file, "r");// 以只读方式打开一个文件
    $comment = '';
    $class = '';
    while (!feof($handle)) {// 函数检测是否已到达文件末尾
        if ($line = fgets($handle)) {// 从文件指针中读取一行
            $line1 = str_replace(' ', '', $line);
            // 注释
            if (isComment($line1)) {
                $comment .= $line;
                continue;
            }
            // 类
            if ($clsName = isClass($line)) {
                $class = $clsName;
                $newComment = getComment('class.' . $class, $comment);
                $newContent .= $newComment;
                $comment = '';    // 注释已使用
            } else if ($function = isFunction($line)) {  // 函数方法
                if (substr($function, 0, 20) == 'PS_UNRESERVE_PREFIX_') {
                    $function = substr($function, 20);
                }
                $blankPre = strpos($line, ' ') === 0;    // 前面空白是类方法的特征
                $function = $class && $blankPre ? $class . '.' . $function : 'function.' . $function;
                $newComment = getComment($function, $comment);
                $newContent .= $newComment;
                $comment = '';    // 注释已使用
            } else if ($const = isConst($line)) {    // 常量
                $newComment = getComment('constant.' . $const, $comment);
                $newContent .= $newComment;
                $comment = '';    // 注释已使用
            } else if ($var = isVar($line)) {  // 预定义变量
                $newComment = getComment('reserved.variables.' . $var, $comment);
                $newContent .= $newComment;
                $comment = '';    // 注释已使用
            }
            // 没有匹配到任何类型内容
            if ($comment) {
                $newContent .= $comment;
                $comment = '';
            }
            $newContent .= $line;
        };
    }
    file_put_contents(DOC_OUT_PATH . $name, $newContent);
}

function handleAll()
{
    $files = [];
    my_dir(DOC_OUT_PATH, '', $files);
    foreach ($files as $file) {
        $suffix = substr(strrchr($file, '.'), 1);
        if ($suffix === 'php') {
            handle($file);
            echo $file . LINE;
        }
    }
}

handleAll();