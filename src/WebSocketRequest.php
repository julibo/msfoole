<?php
namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;

class WebSocketRequest
{

    private $fd;

    private $header;

    private $server;

    private $get;

    private $post;

    private $cookie;

    private $input;

    private $filter;

    private $baseUrl;

    private $url;

    private $host;

    private $pathinfo;

    private $config = [
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter'   => '',
    ];

    public function __construct(array $options = [])
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }
        // 保存 php://input
        $this->input = file_get_contents('php://input');
    }

    /**
     * 设置SERVER数据
     * @access public
     * @param  array $server 数据
     * @return $this
     */
    public function withServer(array $server)
    {
        $this->server = array_change_key_case($server, CASE_UPPER);
        return $this;
    }

    /**
     * 设置GET数据
     * @access public
     * @param  array $get 数据
     * @return $this
     */
    public function withGet(array $get)
    {
        $this->get = $get;
        return $this;
    }

    /**
     * 设置POST数据
     * @access public
     * @param  array $post 数据
     * @return $this
     */
    public function withPost(array $post)
    {
        $this->post = $post;
        return $this;
    }

    /**
     * 设置COOKIE数据
     * @access public
     * @param  array $cookie 数据
     * @return $this
     */
    public function withCookie(array $cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 设置php://input数据
     * @access public
     * @param  string $input RAW数据
     * @return $this
     */
    public function withInput($input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * 设置文件上传数据
     * @access public
     * @param  array $files 上传信息
     * @return $this
     */
    public function withFiles(array $files)
    {
        $this->file = $files;
        return $this;
    }

    /**
     * 设置当前完整URL 不包括QUERY_STRING
     * @access public
     * @param  string $url URL
     * @return $this
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * 设置当前完整URL 包括QUERY_STRING
     * @access public
     * @param  string $url URL
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * 设置当前请求的host（包含端口）
     * @access public
     * @param  string $host 主机名（含端口）
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    public function setPathinfo($pathinfo)
    {
        $this->pathinfo = $pathinfo;
        return $this;
    }


    public function getFd()
    {
        return $this->fd;
    }

    public function getHeader($name = '')
    {
        if ($name == '') {
            return $this->header;
        }
        return $this->header[$name] ?? null;
    }

    public function getServer($name = '')
    {
        if ($name == '') {
            return $this->server;
        }
        return $this->server[$name] ?? null;
    }

    public function getParam($name = '')
    {
        if ($name == '') {
            return $this->get;
        }
        return $this->get[$name] ?? null;
    }

}
