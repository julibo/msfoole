<?php
namespace Julibo\Msfoole;


class Response
{
    /**
     * 原始数据
     * @var mixed
     */
    protected $data;

    /**
     * 应用对象实例
     * @var App
     */
    protected $app;

    /**
     * 当前contentType
     * @var string
     */
    protected $contentType = 'text/html';

    /**
     * 字符集
     * @var string
     */
    protected $charset = 'utf-8';

    /**
     * 状态码
     * @var integer
     */
    protected $code = 200;

    /**
     * 是否允许请求缓存
     * @var bool
     */
    protected $allowCache = true;

    /**
     * 输出参数
     * @var array
     */
    protected $options = [];

    /**
     * header参数
     * @var array
     */
    protected $header = [];

    /**
     * 输出内容
     * @var string
     */
    protected $content = null;

    /**
     * 架构函数
     * @access public
     * @param  mixed $data    输出数据
     * @param  int   $code
     * @param  array $header
     * @param  array $options 输出参数
     */
    public function __construct($data = '', $code = 200, array $header = [], $options = [])
    {
        $this->data($data);

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $this->contentType($this->contentType, $this->charset);

        $this->code   = $code;
        # todo
        # $this->app    = Container::get('app');
        $this->header = array_merge($this->header, $header);
    }

    /**
     * 创建Response对象
     * @access public
     * @param  mixed  $data    输出数据
     * @param  string $type    输出类型
     * @param  int    $code
     * @param  array  $header
     * @param  array  $options 输出参数
     * @return Response
     */
    public static function create($data = '', $type = '', $code = 200, array $header = [], $options = [])
    {
        $class = false !== strpos($type, '\\') ? $type : '\\think\\response\\' . ucfirst(strtolower($type));

        if (class_exists($class)) {
            return new $class($data, $code, $header, $options);
        }

        return new static($data, $code, $header, $options);
    }

    /**
     * 输出数据设置
     * @access public
     * @param  mixed $data 输出数据
     * @return $this
     */
    public function data($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 页面输出类型
     * @access public
     * @param  string $contentType 输出类型
     * @param  string $charset     输出编码
     * @return $this
     */
    public function contentType($contentType, $charset = 'utf-8')
    {
        $this->header['Content-Type'] = $contentType . '; charset=' . $charset;

        return $this;
    }



}