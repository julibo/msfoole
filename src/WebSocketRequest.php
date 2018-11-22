<?php
namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;

class WebSocketRequest
{

    private static $websocketRequest = [];

    private $fd;

    private $header;

    private $server;

    private $request;

    private $cookie;

    private $get;

    private $post;

    private $files;

    private $tmpfiles;

    private $input;

    private $filter;

    private $origin;

    private $key;

    private $query_string;

    private $request_method;

    private $request_uri;

    private $path_info;

    private $config = [
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter'   => '',
    ];

    private function __construct(SwooleRequest $request, array $options = [])
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }
        // 保存 php://input
        $this->input = file_get_contents('php://input');

         $this->withFd($request->fd)
         ->withHeader($request->header)
         ->withServer($request->server)
         ->withGet($request->get);
    }

    public static function getInstance(SwooleRequest $request, array $options = [])
    {
        if (empty(self::$websocketRequest[$request->fd])) {
            self::$websocketRequest[$request->fd] = new static($request, $options);
        }
        return self::$websocketRequest[$request->fd];
    }

    /**
     * 设置客户端ID
     * @param int $fd
     * @return $this
     */
    public function withFd($fd)
    {
        $this->fd = $fd;
        return $this;
    }

    /**
     * 设置头部信息
     * @param array $header
     * @return $this
     */
    public function withHeader(array $header)
    {
        $this->header = array_change_key_case($header);
        $this->origin = $this->header['origin'];
        $this->key = $this->header['sec-websocket-key'];
        return $this;
    }

    /**
     * 设置SERVER数据
     * @access public
     * @param  array $server 数据
     * @return $this
     */
    public function withServer(array $server)
    {
        $this->server = array_change_key_case($server);
        $this->query_string = $this->server['query_string'];
        $this->request_method = $this->server['request_method'];
        $this->request_uri = $this->server['request_uri'];
        $this->path_info = $this->server['path_info'];
        return $this;
    }

    /**
     * 设置request数据
     * @param array $request
     * @return $this
     */
    public function withRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * 设置GET数据
     * @access public
     * @param  array $get 数据
     * @return $this
     */
    public function withGet($get)
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
    public function withPost($post)
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
    public function withCookie($cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 设置文件上传数据
     * @access public
     * @param  array $files 上传信息
     * @return $this
     */
    public function withFiles($files)
    {
        $this->files = $files;
        return $this;
    }

    /**
     * 设置临时文件数据
     * @access public
     * @param  array $tmpfiles
     * @return $this
     */
    public function withTmpFiles($tmpfiles)
    {
        $this->tmpfiles = $tmpfiles;
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
