<?php

/**
 * 临时文件存放目录
 */
const TEMP_PATH = __DIR__ . '/raw/temp/';

/**
 * phpstorm-stubs 目录
 */
const PS_PATH = __DIR__ . '/raw/phpstorm-stubs';

function getComment($file, $oldComment = '', $prefix = '')
{
    // 不是常量替换下划线
    $filePath = TEMP_PATH . (!str_starts_with($file, 'constant.') ? str_replace('_', '-', $file) : $file) . '.html';
    if (is_file($filePath)) {
        $keepLine = '';
        $keepLine2 = '';
        if ($oldComment) {
            $olds = explode(PHP_EOL, $oldComment);
            foreach ($olds as $old) {
                $old2 = ltrim($old);
                // 保留 参数行 和 return行
                if (str_starts_with($old2, '* @param') || str_starts_with($old2, '* @return')) {
                    $keepLine .= PHP_EOL . strip_tags($old);
                } elseif (str_starts_with($old2, '#[')) {
                    $keepLine2 .= PHP_EOL . $old;  // 不去除html标签
                }
            }
        }
        $comment = file_get_contents($filePath);
        $newComment = "$prefix/**" . PHP_EOL . "$prefix * " . $comment . $keepLine . PHP_EOL . "$prefix */";
        if (empty($keepLine2)) $newComment .= $keepLine2 . PHP_EOL;
        return $newComment;
    } else {
        return $oldComment;
    }
}

function isElement($line, $type): false|string
{
    $tokens = explode(' ', $line);
    foreach ($tokens as $k => $v) {
        if ($v == $type && !empty($tokens[$k + 1])) {
            $name = trim($tokens[($k + 1)]);
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
    $pre = '$';
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
    // TODO #[ 开头的有些是在注释下面，有些是在函数和方法里面，待解决
    // foreach (['/**', '* ', '*/', '#['] as $item) {
    foreach (['/**', '* ', '*/'] as $item) {
        if (str_starts_with($line, $item)) return true;
    }
    return false;
}

function handle($filePath): void
{
    $comment = ''; // 旧的注释
    $content = ''; // 新的内容
    $class = '';   // 类名
    $handle = fopen($filePath, 'r');// 以只读方式打开一个文件
    while (!feof($handle)) {// 函数检测是否已到达文件末尾
        if ($line = fgets($handle)) {// 从文件指针中读取一行
            $handleLine = trim($line); // 处理掉首尾空白的行
            if (empty($handleLine)) {
                $content .= $line . PHP_EOL;
                $comment = ''; // 所有空白行不会使用到注释，清空旧的注释
            } elseif (isComment($handleLine)) {// 拿到函数、方法、常量、类等的注释
                $comment .= $line;
                // 注释需要后续处理，所以不需要增加新行
            } else {//
                // ================ 处理注释 start ================ //
                if ($className = isElement($handleLine, 'class')) {// 类名注释
                    $class = $className;
                    $newComment = getComment('class.' . $class, $comment);
                } elseif ($function = isElement($handleLine, 'function')) {// 函数、类方法注释
                    if (str_starts_with($function, 'PS_UNRESERVE_PREFIX_')) $function = substr($function, 20);
                    $blankPre = str_starts_with($line, ' ');    // 前面空白是类方法的特征
                    $function = ($class && $blankPre) ? "$class.$function" : "function.$function";
                    $prefix = ($class && $blankPre) ? '    ' : '';
                    $newComment = getComment($function, $comment, $prefix);
                } elseif ($const = isConst($handleLine)) {// 常量+注释
                    $newComment = getComment('constant.' . $const, $comment);
                } elseif ($var = isVar($handleLine)) {// 预定义变量+注释
                    $newComment = getComment('reserved.variables.' . $var, $comment);
                }
                // ================ 处理注释 end ================ //
                $content .= $newComment ?? $comment;
                $content .= $line . PHP_EOL;
                $comment = '';    // 旧的注释已使用，清空旧的注释
            }
        }
    }
    file_put_contents($filePath, $content);
}

/**
 * 递归获取所有目录
 * @param string $parent 父级目录 /a/b/c.html
 * @param string $dirPath
 * @return void
 */
function run(string $parent = '', string $dirPath = PS_PATH): void
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