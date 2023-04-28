<?php
// +----------------------------------------------------------------------
// | php-doc
// +----------------------------------------------------------------------
// | Copyright (c) 2022-2023 linjialiang All rights reserved.
// +----------------------------------------------------------------------
// | Author: linjialiang <linjialiang@163.com>
// +----------------------------------------------------------------------
// | CreateTime: 2023-04-28 09:27:31
// +----------------------------------------------------------------------


// +----------------------------------------------------------------------
// | 帮助函数
// +----------------------------------------------------------------------
// | 从文件中获取html dom
// +----------------------------------------------------------------------
// | $maxlen 在代码中定义为 PHP_STREAM_COPY_ALL，定义为-1
// +----------------------------------------------------------------------
function file_get_html($url, $use_include_path = false, $context = null, $offset = 0, $maxLen = -1, $lowercase = true, $forceTagsClosed = true, $target_charset = DEFAULT_TARGET_CHARSET, $stripRN = true, $defaultBRText = DEFAULT_BR_TEXT, $defaultSpanText = DEFAULT_SPAN_TEXT)
{
    // Ensure maximum length is greater than zero
    if ($maxLen <= 0) {
        $maxLen = MAX_FILE_SIZE;
    }

    // We DO force the tags to be terminated.
    $dom = new simple_html_dom(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
    // For sourceforge users: uncomment the next line and comment the retrieve_url_contents line 2 lines down if it is not already done.
    $contents = file_get_contents($url, $use_include_path, $context, $offset, $maxLen);
    // Paperg - use our own mechanism for getting the contents as we want to control the timeout.
    //$contents = retrieve_url_contents($url);
    if (empty($contents) || strlen($contents) > $maxLen) {
        return false;
    }
    // The second parameter can force the selectors to all be lowercase.
    $dom->load($contents, $lowercase, $stripRN);
    return $dom;
}

// +----------------------------------------------------------------------
// | 从字符串中获取 html dom
// +----------------------------------------------------------------------
function str_get_html($str, $lowercase = true, $forceTagsClosed = true, $target_charset = DEFAULT_TARGET_CHARSET, $stripRN = true, $defaultBRText = DEFAULT_BR_TEXT, $defaultSpanText = DEFAULT_SPAN_TEXT)
{
    $dom = new simple_html_dom(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
    if (empty($str) || strlen($str) > MAX_FILE_SIZE) {
        $dom->clear();
        return false;
    }
    $dom->load($str, $lowercase, $stripRN);
    return $dom;
}

// +----------------------------------------------------------------------
// | 转储html dom树
// +----------------------------------------------------------------------
function dump_html_tree($node, $show_attr = true, $deep = 0)
{
    $node->dump($node);
}