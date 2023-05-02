<?php

const DOC_IN_PATH = __DIR__ . '/../raw/temp/';
const PS_DOC_OUT_PATH = __DIR__ . '/../raw/phpstorm-stubs';
const LINE = "\n";

function getComment($file, $oldComment = '')
{
    // 不是常量替换下划线
    $filePath = DOC_IN_PATH . (!str_starts_with($file, 'constant.') ? str_replace('_', '-', $file) : $file) . '.html';
    if (is_file($filePath)) {
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
        $comment = file_get_contents($filePath);
        return '/**' . LINE . ' * ' . $comment . $keepLine . ' */' . $keepLine2 . LINE;
    }
    return $oldComment;
}

function isElement($line, $type): false|string
{
    $tokens = explode(' ', trim($line));
    foreach ($tokens as $k => $v) {
        if ($v == $type) {
            $name = trim($tokens[$k + 1]);
            var_dump($name);
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

function handle($filePath): void
{
    $oldComment = '';
    $newContent = '';
    $class = '';
    $handle = fopen($filePath, 'r');// 以只读方式打开一个文件
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
                if (false === $newComment) continue;
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用，清空
            } elseif ($function = isElement($line, 'function')) {// 函数和类方法+注释
                if (str_starts_with($function, 'PS_UNRESERVE_PREFIX_')) $function = substr($function, 20);
                $blankPre = str_starts_with($line, ' ');    // 前面空白是类方法的特征
                $function = $class && $blankPre ? $class . '.' . $function : 'function.' . $function;
                $newComment = getComment($function, $oldComment);
                if (false === $newComment) continue;
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用，清空
            } elseif ($const = isConst($line)) {// 常量+注释
                $newComment = getComment('constant.' . $const, $oldComment);
                if (false === $newComment) continue;
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用，清空
            } elseif ($var = isVar($line)) {// 预定义变量+注释
                $newComment = getComment('reserved.variables.' . $var, $oldComment);
                if (false === $newComment) continue;
                $newContent .= $newComment;
                $oldComment = '';    // 注释已使用，清空
            }
            $newContent .= $line;
        };
    }
    file_put_contents($filePath, $newContent);
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
        if ('..' === $file || '.' === $file) continue;  // 排除根目录
        $filePath = "$dirPath/$file"; // 文件全路径
        if (is_dir($filePath)) {// 如果是目录，就进行递归获取文件
            run("$parent/$file", $filePath);
        } elseif ('php' === substr(strrchr($file, '.'), 1)) {// 处理文件
            handle($filePath);
        }
    }
    closedir($handle);
}

run();