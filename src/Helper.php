<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的多进程API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

class Helper
{
    public static function  guid()
    {
        if (function_exists('com_create_guid')) {
            $uuid = com_create_guid();
        } else {
            mt_srand((double)microtime()*10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid   = chr(123)
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);
        }
        $uuid = str_replace(array('-', '{', '}'), '', $uuid);
        return $uuid;
    }

    /**
     * 判断字符串是否为 Json 格式
     *
     * @param  string     $data  Json 字符串
     * @param  bool       $assoc 是否返回关联数组。默认返回对象
     *
     * @return bool|array 成功返回转换后的对象或数组，失败返回 false
     */
    public static function isJson($data = '', $assoc = false) {
        $data = json_decode($data, $assoc);
        if ($data && (is_object($data)) || (is_array($data) && !empty(current($data)))) {
            return $data;
        }
        return false;
    }

    public static function setProcessTitle($title)
    {
        if (!IS_DARWIN && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($title);
        }
    }

}
