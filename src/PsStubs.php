<?php
// +----------------------------------------------------------------------
// | phpstorm-stubs 处理类-加入PHP官方手册说明
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-05-06 20:34:56
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace src;

class PsStubs
{
    /**
     * 临时文件存放目录
     */
    const TEMP_PATH = __DIR__ . '/../raw/temp/';

    /**
     * phpstorm-stubs 目录
     */
    const PS_PATH = __DIR__ . '/../raw/phpstorm-stubs';

    public function __construct()
    {
    }


    private function getComment($file, $oldComment, $info)
    {
        // 不是常量替换下划线
        $filePath = TEMP_PATH . (!str_starts_with($file, 'constant.') ? str_replace('_', '-', $file) : $file) . '.html';
        $filePath = strtolower($filePath); // 大写转小写
        if (is_file($filePath) && !empty($oldComment)) {
            $keepLine = '';
            $keepLine2 = '';
            $isAttribute = false;   // 注解，是否注解
            $olds = explode(PHP_EOL, $oldComment);
            $prefix = $olds[0] === ltrim($olds[0]) ? '' :
                substr($olds[0], 0, strlen($olds[0]) - strlen(ltrim($olds[0])));
            foreach ($olds as $old) {
                $old_trim = trim($old);
                // 保留 参数行 和 return行
                if ($isAttribute) {
                    $keepLine2 .= PHP_EOL . $old;  // 不去除html标签
                    if (in_array($old_trim, [')]', '])]'])) $isAttribute = false;
                } elseif (str_starts_with($old_trim, '* @param') || str_starts_with($old_trim, '* @return')) {
                    $keepLine .= PHP_EOL . strip_tags($old);
                } elseif (str_starts_with($old_trim, '#[')) {
                    if (!str_ends_with($old_trim, ']')) $isAttribute = true;
                    $keepLine2 .= PHP_EOL . $old;  // 不去除html标签
                }
            }
            $html = file_get_contents($filePath);
            $newComment = "$prefix/**" . PHP_EOL . "$prefix * " . $html . $keepLine . PHP_EOL . "$prefix */";
            if (!empty($keepLine2)) $newComment .= $keepLine2;
            return $newComment . PHP_EOL;
        }
        return $oldComment;
    }

    /**
     * 保存文件
     * @param string $filePath
     * @param string $content
     * @param bool $isAppend 是否追加写入 默认false
     * @return void
     */
    private function save_file(string $filePath, string $content, bool $isAppend = false): void
    {
        $handle = fopen($filePath, $isAppend ? 'a+' : 'w+');
        fwrite($handle, $content);
        fclose($handle);
    }

    /**
     * 验证类名 interface trait class
     * @param $buffer
     * @return false|array
     */
    private function isClass($buffer): false|array
    {
        foreach (['interface', 'trait', 'class', 'abstract class', 'final class'] as $item) {
            if (str_starts_with($buffer, "$item ")) { // $item 结尾带空格
                $tokens = explode(' ', $buffer);
                foreach ($tokens as $key => $value) {
                    if (in_array($value, ['class', 'interface', 'trait'])) {
                        $nextKey = $key + 1;
                        return !empty($tokens[$nextKey]) ?
                            ['name' => explode('(', $tokens[$nextKey])[0], 'prefix' => $item] : false;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 验证函数名、方法名
     * @param $buffer
     * @return false|array
     */
    private function isMethod($buffer): false|array
    {
        $list = [
            'function',

            'public function',
            'abstract public function',
            'public static function',
            'final public function',
            'final public static function',

            'protected function',
            'protected static function',
            'abstract protected function',
            // 'final protected function', // 没有
            // 'final protected static function', // 没有

            'private function',
            // 'abstract private function', // 没有
            'private static function',
            'final private function',
            'final private static function',
        ];
        foreach ($list as $item) {
            if (str_starts_with($buffer, "$item ")) { // $item 结尾带空格
                $tokens = explode(' ', $buffer);
                foreach ($tokens as $key => $value) {
                    if ($value === 'function') {
                        $nextKey = $key + 1;
                        return !empty($tokens[$nextKey]) ?
                            [
                                'name' => str_replace('__', '', explode('(', $tokens[$nextKey])[0]),
                                'prefix' => $item
                            ] : false;
                    }
                }
            }
        }
        return false;
    }

// 暂不验证
    private function isConst($buffer): false|string
    {
        $buffer = str_replace(' ', '', $buffer);
        $pre = "define('";
        if (str_starts_with($buffer, $pre)) {
            $buffer = str_replace($pre, '', $buffer);
            return explode("'", $buffer)[0];
        }
        return false;
    }

// 暂不验证
    private function isVar($buffer): false|string
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
     * @param bool|array $methodInfo
     * @param bool|array $attributeInfo
     * @return array|bool
     */
    private function isComment(string $buffer, bool|array $methodInfo = false, bool|array $attributeInfo = false): array|bool
    {
        if ($attributeInfo) {
            return in_array($buffer, [')]', '])]']) ? ['isAttribute' => false] : true;
        } else {
            foreach (['/**', '*', '*/'] as $item) {
                if (str_starts_with($buffer, $item)) return true;
            }
            if (str_starts_with($buffer, '#[') && !$methodInfo)
                return !str_ends_with($buffer, ']') ? ['isAttribute' => true] : true;
        }
        return false;
    }

    private function handle($filePath): void
    {
        $fp = @fopen($filePath, 'r');
        if ($fp) {
            $classInfo = false;   // 类名信息
            $methodInfo = false;   // 函数、方法名信息
            $isAttribute = false;   // 注解，注释下的注解归入注释
            $content = ''; // 新的内容
            $oldComment = ''; // 旧的注释
            // 以只读方式打开一个文件
            while (false !== ($buffer = fgets($fp, 4096))) { // 从文件指针中读取一行，带换行符
                $buffer_trim = trim($buffer); // 处理掉行首空白的行
                if (empty($buffer_trim)) {
                    $content .= $oldComment; // 可能存在旧的注释
                    $content .= $buffer;     // 保留空白行
                    $oldComment = '';        // 所有空白行不会使用到注释，清空旧的注释
                    $methodInfo = false;     // 遇到空行将 $methodInfo 设为 false
                    $isAttribute = false;     // 非注释将注解信息设为 false
                } elseif ($commentInfo = isComment($buffer_trim, $methodInfo, $isAttribute)) { // 拿到函数、方法、类的注释
                    if (is_array($commentInfo)) $isAttribute = $commentInfo['isAttribute'];
                    $oldComment .= $buffer; // 注释需要后续处理，所以不需要增加新行
                } else {
                    $isAttribute = false;     // 非注释将注解信息设为 false
                    // ================ 处理注释 start ================ //
                    if (false !== ($info = isClass($buffer_trim))) { // 类名
                        $classInfo = $info;
                        unset($info);
                        $newComment = getComment('class.' . $classInfo['name'], $oldComment, $classInfo);
                    } elseif (false !== ($info = isMethod($buffer_trim))) { // 函数名、类方法名
                        $methodInfo = $info;
                        unset($info);
                        $function = str_starts_with($methodInfo['name'], 'PS_UNRESERVE_PREFIX_') ?
                            substr($methodInfo['name'], 20) : $methodInfo['name'];
                        $file = $methodInfo['prefix'] === 'function' ?
                            "function.$function" : "{$classInfo['name']}.$function";
                        $newComment = getComment($file, $oldComment, $methodInfo);
                    } elseif (str_starts_with($buffer_trim, '):') || str_starts_with($buffer_trim, ') {')) {
                        $methodInfo = false; // 以 ')' 结尾代表一个方法结束
                    }
                    // elseif ($const = isConst($buffer_trim)) {// 常量
                    //
                    // } elseif ($var = isVar($buffer_trim)) {// 预定义变量
                    //
                    // } elseif (str_starts_with($buffer_trim, ')')) {//
                    //     $passMethod = false; // 以 ')' 结尾代表一个方法结束
                    // }
                    // ================ 处理注释 end ================ //
                    $content .= $newComment ?? $oldComment;
                    $content .= $buffer;
                    $oldComment = '';    // 旧的注释已使用，清空旧的注释
                    unset($newComment);  // 新的注释已使用，回收新的注释
                }
            }
            // 函数检测是否已到达文件末尾
            if (!feof($fp)) echo "$filePath Error: unexpected fgets() fail\n";
            fclose($fp);
            file_put_contents($filePath, $content);
        }
    }

    /**
     * 递归获取所有目录
     * @param string $parent 父级目录 /a/b/c.html
     * @param string $dirPath
     * @return void
     */
    public function run(string $parent = '', string $dirPath = PS_PATH): void
    {
        $handle = @opendir($dirPath);
        if (!$handle) exit('目录打开失败');
        while (false !== ($file = readdir($handle))) {
            if ('..' === $file || '.' === $file) continue;  // 排除根目录
            $filePath = "$dirPath/$file"; // 文件全路径
            if (is_dir($filePath)) { // 如果是目录，就进行递归获取文件
                run("$parent/$file", $filePath);
            } elseif ('php' === substr(strrchr($file, '.'), 1)) { // 处理文件
                handle($filePath);
            }
        }
        closedir($handle);
    }

}