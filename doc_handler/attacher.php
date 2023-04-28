<?php

const DOC_IN_PATH = __DIR__ . '/../raw/temp/';
const DOC_OUT_PATH = __DIR__ . '/../raw/phpstorm-stubs';
const LINE = "\n";

/**
 * @param ...$args
 * @return void
 */
function myPrint(...$args): void
{
    foreach ($args as $arg) {
        print_r($arg);
        echo PHP_EOL;
    }
}

/**
 * @param $dir
 * @param string $parent
 * @param array $files
 * @return array|void
 */
function my_dir($dir, string $parent = '', array &$files = [])
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

/**
 * @param $line
 * @return bool
 */
function isComment($line): bool
{
    foreach (['/*', '*', '*/', '#'] as $item) {
        if (str_starts_with($line, $item)) {
            return true;
        }
    }
    return false;
}

/**
 * @param $token
 * @param $oldComment
 * @return mixed|string
 */
function getComment($token, $oldComment): mixed
{
    if (!str_starts_with($token, 'constant.'))  // 不是常量替换下划线
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
                    str_starts_with($old2, '* @param') ||  // 保留参数行
                    str_starts_with($old2, '* @return')    // 保留return行
                ) {
                    $keepLine .= strip_tags($old) . LINE;
                } elseif (str_starts_with($old2, '#[')) {
                    $keepLine2 .= LINE . strip_tags($old);
                }
            }
        }
        if (!empty($keepLine)) {
            $keepLine = LINE . $keepLine;
        }
        $comment = file_get_contents($file);
        return '/**' . LINE . ' * ' . $comment . $keepLine . ' */' . $keepLine2 . LINE;
    } else {
        return $oldComment;
    }
}

/**
 * @param $line
 * @param $type
 * @return false|string
 */
function isElement($line, $type): false|string
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

/**
 * @param $line
 * @return false|string
 */
function isClass($line): false|string
{
    return isElement($line, 'class');
}

/**
 * @param $line
 * @return false|string
 */
function isFunction($line): false|string
{
    return isElement($line, 'function');
}

/**
 * @param $line
 * @return false|string
 */
function isConst($line): false|string
{
    $line = str_replace(' ', '', $line);
    $pre = "define('";
    if (str_starts_with($line, $pre)) {
        $line = str_replace($pre, '', $line);
        return explode("'", $line)[0];
    }
    return false;
}

/**
 * @param $line
 * @return false|string
 */
function isVar($line): false|string
{
    $line = str_replace(' ', '', $line);
    $pre = "$";
    if (str_starts_with($line, $pre)) {
        $line = str_replace($pre, '', $line);
        $line = str_replace('_', '', $line);
        return explode("=", $line)[0];
    }
    return false;
}

/**
 * @param $name
 * @return void
 */
function handle($name): void
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
                if (str_starts_with($function, 'PS_UNRESERVE_PREFIX_')) {
                    $function = substr($function, 20);
                }
                $blankPre = str_starts_with($line, ' ');    // 前面空白是类方法的特征
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
        }
    }
    file_put_contents(DOC_OUT_PATH . $name, $newContent);
}

/**
 * @return void
 */
function handleAll(): void
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