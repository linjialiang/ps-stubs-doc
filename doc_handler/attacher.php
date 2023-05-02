<?php

const DOC_IN_PATH = __DIR__ . '/../raw/temp/';
const PS_DOC_OUT_PATH = __DIR__ . '/../raw/phpstorm-stubs';
const LINE = "\n";

function getComment($token, $oldComment = '')
{
    // 不是常量替换下划线
    if (!str_starts_with($token, 'constant.')) $token = str_replace('_', '-', $token);
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

function isElement($line, $type): false|string
{
    $tokens = explode(' ', trim($line));
    foreach ($tokens as $k => $v) {
        if ($v == $type) {
            $name = trim($tokens[$k + 1]);
            return strpos($name, '(') ? substr($name, 0, strpos($name, '(')) : $name;
        }
    }
    return false;
}

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
 * 验证是否是注释
 * @param string $line
 * @return bool
 */
function isComment(string $line): bool
{
    foreach (['/**', '* ', '*/', '#['] as $item) {
        if (str_starts_with(trim($line), $item)) return true;
    }
    return false;
}

function handle($fileName, $allPath): void
{
    $newContent = '';
    $class = '';
    $oldComment = '';
    $handle = fopen($allPath, "r");// 以只读方式打开一个文件
    while (!feof($handle)) {// 函数检测是否已到达文件末尾
        if ($line = fgets($handle)) {// 从文件指针中读取一行
            // 拿到函数、方法、常量等的注释
            if (isComment($line)) {
                $oldComment .= $line;
                continue;
            }
            // 注释转中文
            if ($className = isElement($line, 'class')) {// 类名+类名注释
                $class = $className;
                $newComment = getComment('class.' . $class, $oldComment);
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用
            } elseif ($function = isElement($line, 'function')) {// 函数和类方法+注释
                if (str_starts_with($function, 'PS_UNRESERVE_PREFIX_')) $function = substr($function, 20);
                $blankPre = str_starts_with($line, ' ');    // 前面空白是类方法的特征
                $function = $class && $blankPre ? $class . '.' . $function : 'function.' . $function;
                $newComment = getComment($function, $oldComment);
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用
            } elseif ($const = isConst($line)) {// 常量+注释
                $newComment = getComment('constant.' . $const, $oldComment);
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用
            } elseif ($var = isVar($line)) {// 预定于变量+注释
                $newComment = getComment('reserved.variables.' . $var, $oldComment);
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用
            }
            $newContent .= $line;
        };
    }
    file_put_contents(PS_DOC_OUT_PATH . $fileName, $newContent);
}

/**
 * 递归获取所有目录
 * @param string $parent 父级目录 /a/b/c.html
 * @param string $dirPath
 * @return void
 */
function run(string $parent = '', string $dirPath = PS_DOC_OUT_PATH): void
{
    $handle = @opendir($dirPath);
    if (!$handle) exit('目录打开失败');
    while (false !== ($file = readdir($handle))) {
        if ($file != ".." && $file != ".") continue;  // 排除根目录
        $allPath = "$dirPath/$file"; // 文件全路径
        if (is_dir($allPath)) {// 如果是目录，就进行递归获取文件
            run("$parent/$file", $allPath);
        } else {// 处理文件
            if ('php' === substr(strrchr($file, '.'), 1)) handle($file, $allPath);
        }
    }
    closedir($handle);
}

run();