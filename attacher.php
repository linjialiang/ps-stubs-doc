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

function isElement($buffer, $type): false|string
{
    $tokens = explode(' ', $buffer);
    foreach ($tokens as $k => $v) {
        if ($v == $type && !empty($tokens[$k + 1])) {
            $name = trim($tokens[($k + 1)]);
            return strpos($name, '(') ? substr($name, 0, strpos($name, '(')) : $name;
        }
    }
    return false;
}

function isConst($buffer): false|string
{
    $buffer = str_replace(' ', '', $buffer);
    $pre = "define('";
    if (str_starts_with($buffer, $pre)) {
        $buffer = str_replace($pre, '', $buffer);
        return explode("'", $buffer)[0];
    }
    return false;
}

function isVar($buffer): false|string
{
    $buffer = str_replace(' ', '', $buffer);
    $pre = '$';
    if (str_starts_with($buffer, $pre)) {
        $buffer = str_replace($pre, '', $buffer);
        $buffer = str_replace('_', '', $buffer);
        return explode("=", $buffer)[0];
    }
    return false;
}

/**
 * 验证是否是注释
 * @param string $buffer
 * @param bool $passMethod
 * @return bool
 */
function isComment(string $buffer, bool $passMethod = false): bool
{
    foreach (['/**', '* ', '*/'] as $item) {
        if (str_starts_with($buffer, $item)) return true;
    }
    return str_starts_with($buffer, '#[') && !$passMethod;
}

function handle($filePath): void
{
    $fp = @fopen($filePath, 'r');
    if ($fp) {
        $class = '';   // 类名
        $content = ''; // 新的内容
        $oldComment = ''; // 旧的注释
        $passMethod = false; // 是否到达函数、类方法，用于解决 #[ 在方法内部问题
        // 以只读方式打开一个文件
        while (false !== ($buffer = fgets($fp, 4096))) {// 从文件指针中读取一行，带换行符
            $buffer_ltrim = ltrim($buffer); // 处理掉行首空白的行
            if (empty($buffer_ltrim)) {
                $passMethod = false; // 遇到空行将 $passMethod 设为false
                $content .= $buffer;
                $oldComment = ''; // 所有空白行不会使用到注释，清空旧的注释
            } elseif (isComment($buffer_ltrim, $passMethod)) {// 拿到函数、方法、常量、类等的注释
                $oldComment .= $buffer;
                // 注释需要后续处理，所以不需要增加新行
            } else {//
                // ================ 处理注释 start ================ //
                if ($className = isElement($buffer_ltrim, 'class')) {// 类名注释
                    $class = $className;
                    $newComment = getComment('class.' . $class, $oldComment);
                } elseif ($function = isElement($buffer_ltrim, 'function')) {// 函数、类方法注释
                    $passMethod = true;
                    if (str_starts_with($function, 'PS_UNRESERVE_PREFIX_')) $function = substr($function, 20);
                    $blankPre = str_starts_with($buffer, ' ');    // 前面空白是类方法的特征
                    $function = ($class && $blankPre) ? "$class.$function" : "function.$function";
                    $prefix = ($class && $blankPre) ? '    ' : '';
                    $newComment = getComment($function, $oldComment, $prefix);
                } elseif ($const = isConst($buffer_ltrim)) {// 常量+注释
                    $newComment = getComment('constant.' . $const, $oldComment);
                } elseif ($var = isVar($buffer_ltrim)) {// 预定义变量+注释
                    $newComment = getComment('reserved.variables.' . $var, $oldComment);
                } elseif (str_starts_with($buffer_ltrim, ')')) {
                    $passMethod = false; // 以 ')' 结尾代表一个方法结束
                }
                // ================ 处理注释 end ================ //
                $content .= $newComment ?? $oldComment;
                $content .= $buffer . PHP_EOL;
                $oldComment = '';    // 旧的注释已使用，清空旧的注释
            }
        }
        // 函数检测是否已到达文件末尾
        if (!feof($fp)) echo "$filePath Error: unexpected fgets() fail\n";
        fclose($fp);
        // var_dump($content);die;
        // var_dump($filePath);
        file_put_contents($filePath, $content);
        // die;
    }
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