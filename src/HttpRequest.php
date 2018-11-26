<?php
namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;

class HttpRequest
{

    private $filter;

    private $header;

    private $server;

    private $get;

    private $post;

    private $cookie;

    private $input;

    private $getData;

    private $host;

    private $origin;

    private $request_uri;

    private $query_string;

    private $request_method;

    private $path_info;

    private $server_port;

    private $remote_addr;

    private $config = [
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter' => '',
    ];

    public function __construct(SwooleRequest $request, array $options = [])
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }

        $this->withHeader($request->header)
            ->withServer($request->server)
            ->withGet($request->get)
            ->withPost($request->post)
            ->withCookie($request->cookie)
            ->withInput($request->rawContent)
            ->withData($request->getData);
    }

    private function withHeader($header)
    {
        $this->header = $header;
        $this->host = $this->header['host'];
        $this->origin = $this->header['origin'];
        return $this;
    }

    private function withServer($server)
    {
        $this->server = $server;
        $this->query_string = $this->server['query_string'];
        $this->request_method = $this->server['request_method'];
        $this->request_uri = $this->server['request_uri'];
        $this->path_info = $this->server['path_info'];
        $this->server_port = $this->server['server_port'];
        $this->remote_addr = $this->server['remote_addr'];
        return $this;
    }

    private function withGet($get)
    {
        $this->get = $get;
        return $this;
    }

    private function withPost($post)
    {
        $this->post = $post;
        return $this;
    }

    private function withCookie($cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    private function withInput($input)
    {
        $this->input = $input;
        return $this;
    }

    private function withData($data)
    {
        $this->getData = $data;
        return $this;
    }

    public function getRequestMethod()
    {
        return $this->request_method;
    }

    public function getPathInfo()
    {
        return $this->path_info;
    }

    public function getRequestUri()
    {
        return $this->request_uri;
    }

    public function getQueryString()
    {
        return $this->query_string;
    }

    public function getRemoteAddr()
    {
        return $this->remote_addr;
    }

    public function getParams()
    {
        return $this->get;
    }

    public function getPost()
    {
        return $this->post;
    }

    public function getCookie()
    {
        return $this->cookie;
    }
}
