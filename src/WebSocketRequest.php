<?php
namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;

class WebSocketRequest
{

    private static $websocketRequest = [];

    private $fd;

    private $token;

    private $header;

    private $server;

    private $get;

    private $filter;

    private $origin;

    private $query_string;

    private $request_method;

    private $request_uri;

    private $path_info;

    private $config = [
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter' => '',
    ];

    private function __construct(SwooleRequest $request, array $options = [])
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }

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

    public static function destroy(self $request)
    {
        $fd = $request->getFd();
        if ($fd && isset(self::$websocketRequest[$fd]))
            unset(self::$websocketRequest[$fd]);
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
     * 设置Token
     * @param $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
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

    public function getToken()
    {
        return $this->token;
    }

    /**
     * 获取请求参数
     * @param string $name
     * @return array|mixed|null
     */
    public function getQuery($name = '')
    {
        if (empty($this->query_string)) {
            return null;
        }
        $params = [];
        $query = explode('&', $this->query_string);
        foreach ($query as $vo) {
            $arr = explode('=', $vo, 2);
            $params[$arr[0]] = $arr[1];
        }
        if (empty($name)) {
            return $params;
        } else {
            if (isset($params[$name]))
                return $params[$name];
            else
                return null;
        }
    }

    public function __get($name)
    {
        if (isset($this->$name))
            return $this->$name;
        else
            return null;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
        return $this->$name;
    }
}
