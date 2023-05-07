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
    private const TEMP_PATH = __DIR__ . '/../raw/temp/';

    /**
     * phpstorm-stubs 目录
     */
    private const PS_PATH = __DIR__ . '/../raw/phpstorm-stubs';

    /**
     * @var string 新的内容
     */
    private string $content;

    /**
     * @var string 旧的注释
     */
    private string $oldComment;

    /**
     * @var array 类名信息
     */
    private array $classInfo;

    /**
     * @var array 函数、方法名信息
     */
    private array $methodInfo;

    /**
     * @var string 变量信息
     */
    private string $varInfo;


    /**
     * @var bool 注解，注释下的注解归入注释
     */
    private bool $isAttribute;

    /**
     * 递归获取所有目录
     * @param string $parent 父级目录 /a/b/c.html
     * @return void
     */
    public function run(string $parent = ''): void
    {
        $handle = @opendir(self::PS_PATH . "/$parent");
        if (!$handle) exit('目录打开失败');
        while (false !== ($file = readdir($handle))) {
            if ('..' === $file || '.' === $file) continue;  // 排除根目录
            $filePath = self::PS_PATH . "$parent/$file"; // 文件全路径
            if (is_dir($filePath)) { // 如果是目录，就进行递归获取文件
                $this->run("$parent/$file");
            } elseif (str_ends_with($file, '.php')) {// 处理文件
                $this->handle($filePath);
            }
        }
        closedir($handle);
    }

    /**
     * 处理单个文件
     * @param string $filePath 文件全路径
     * @return void
     */
    private function handle(string $filePath): void
    {
        $fp = @fopen($filePath, 'r');
        if ($fp) {
            $this->clean();
            // 以只读方式打开一个文件
            while (false !== ($buffer = fgets($fp, 4096))) { // 从文件指针中读取一行，带换行符
                $buffer_trim = trim($buffer); // 处理掉行首空白的行
                if (empty($buffer_trim)) {// 空白行
                    $this->content .= $this->oldComment; // 可能存在旧的注释
                    $this->content .= $buffer;           // 保留空白行
                    $this->oldComment = '';              // 所有空白行不会使用到注释，清空旧的注释
                    $this->methodInfo = [];              // 遇到空行将 methodInfo 设为 []
                    $this->isAttribute = false;          // 非注释将注解信息设为 false
                } elseif ($this->isComment($buffer_trim)) {// 拿到函数、方法、类的注释
                    $this->oldComment .= $buffer; // 注释需要后续处理，所以不需要增加新行
                } else {
                    $this->isAttribute = false;     // 非注释将注解信息设为 false
                    // ================ 处理注释 start ================ //
                    if ($this->isClass($buffer_trim)) { // 类名
                        $newComment = $this->getComment('class.' . $this->classInfo['name']);
                    } elseif ($this->isMethod($buffer_trim)) { // 函数名、类方法名
                        $function = str_starts_with($this->methodInfo['name'], 'PS_UNRESERVE_PREFIX_') ?
                            substr($this->methodInfo['name'], 20) : $this->methodInfo['name'];
                        $file = $this->methodInfo['prefix'] === 'function' ?
                            "function.$function" : "{$this->classInfo['name']}.$function";
                        $newComment = $this->getComment($file);
                    } elseif (str_starts_with($buffer_trim, '):') || str_starts_with($buffer_trim, ') {')) {
                        $this->methodInfo = []; // 以 ')' 结尾代表一个方法结束
                    } elseif ($this->isVar($buffer_trim)) {
                        $newComment = $this->getComment('reserved.variables.' . $this->varInfo);
                        $this->varInfo = '';  // 变量只有一行，所以干完活就可以清空
                    }
                    // elseif ($const = isConst($buffer_trim)) {// 常量
                    //
                    // } elseif ($var = isVar($buffer_trim)) {// 预定义变量
                    //
                    // } elseif (str_starts_with($buffer_trim, ')')) {//
                    //     $passMethod = false; // 以 ')' 结尾代表一个方法结束
                    // }
                    // ================ 处理注释 end ================ //
                    $this->content .= $newComment ?? $this->oldComment;
                    $this->content .= $buffer;
                    $this->oldComment = '';    // 旧的注释已使用，清空旧的注释
                    unset($newComment);  // 新的注释已使用，回收新的注释
                }
            }
            // 函数检测是否已到达文件末尾
            if (!feof($fp)) echo "$filePath Error: unexpected fgets() fail\n";
            fclose($fp);
            file_put_contents($filePath, $this->content);
        }
    }

    /**
     * 清空属性
     * @return void
     */
    private function clean(): void
    {
        $this->content = '';
        $this->oldComment = '';
        $this->classInfo = [];
        $this->methodInfo = [];
        $this->varInfo = '';
        $this->isAttribute = false;
    }

    /**
     * 获取新的注释
     * @param string $file 文件名称
     * @return string
     */
    private function getComment(string $file): string
    {
        // 不是常量替换下划线
        $filePath = self::TEMP_PATH . (!str_starts_with($file, 'constant.') ? str_replace('_', '-', $file) : $file) . '.html';
        $filePath = strtolower($filePath); // 大写转小写
        if (is_file($filePath) && !empty($this->oldComment)) {
            $keepLine = '';
            $keepLine2 = '';
            $isAttribute = false;   // 注解，是否注解
            $olds = explode(PHP_EOL, $this->oldComment);
            $prefix = $olds[0] === ltrim($olds[0]) ? '' :
                substr($olds[0], 0, strlen($olds[0]) - strlen(ltrim($olds[0])));
            foreach ($olds as $old) {
                $old_trim = trim($old);
                // 保留 参数行 和 return行
                if ($isAttribute) {
                    $keepLine2 .= PHP_EOL . $old;  // 不去除html标签
                    if (in_array($old_trim, [')]', '])]'])) $isAttribute = false;
                } elseif ($this->isKeep($old_trim)) {
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
        return $this->oldComment;
    }

    /**
     * 是否保留注释
     * @param string $line trim后的1行注释
     * @return bool
     */
    private function isKeep(string $line): bool
    {
        foreach (['* @param', '* @return', '* @xglobal', '* @deprecated', '* @removed'] as $item) {
            if (str_starts_with($line, "$item ")) return true;
        }
        return false;
    }

    /**
     * 验证类名 interface trait class
     * @param string $buffer
     * @return bool
     */
    private function isClass(string $buffer): bool
    {
        foreach (['interface', 'trait', 'class', 'abstract class', 'final class'] as $item) {
            if (str_starts_with($buffer, "$item ")) { // $item 结尾带空格
                $tokens = explode(' ', $buffer);
                foreach ($tokens as $key => $value) {
                    if (in_array($value, ['class', 'interface', 'trait'])) {
                        $nextKey = $key + 1;
                        if (!empty($tokens[$nextKey])) {
                            $this->classInfo = [
                                'name' => explode('(', $tokens[$nextKey])[0],
                                'prefix' => $item
                            ];
                            return true;
                        }
                        return false;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 验证函数名、方法名
     * @param string $buffer
     * @return bool
     */
    private function isMethod(string $buffer): bool
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
                        if (!empty($tokens[$nextKey])) {
                            $this->methodInfo = [
                                'name' => str_replace('__', '', explode('(', $tokens[$nextKey])[0]),
                                'prefix' => $item
                            ];
                            return true;
                        }
                        return false;
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

    /**
     * 验证预定义常量
     * @param string $buffer
     * @return false|string
     */
    private function isVar(string $buffer): false|string
    {
        $prefix = '$';
        if (str_starts_with($buffer, $prefix)) {
            $this->varInfo = str_replace([$prefix, '_'], '', explode(' ', $buffer)[0]);
            return true;
        }
        return false;
    }

    /**
     * 验证是否是注释
     * @param string $buffer
     * @return bool
     */
    private function isComment(string $buffer): bool
    {
        if ($this->isAttribute) {
            if (in_array($buffer, [')]', '])]'])) $this->isAttribute = false;
            return true;
        } else {
            foreach (['/**', '*', '*/'] as $item) {
                if (str_starts_with($buffer, $item)) return true;
            }
            if (str_starts_with($buffer, '#[') && !$this->methodInfo) {
                if (!str_ends_with($buffer, ']')) $this->isAttribute = true;
                return true;
            }
        }
        return false;
    }


}