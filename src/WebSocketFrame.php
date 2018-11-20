<?php
namespace Julibo\Msfoole;

class WebSocketFrame implements \ArrayAccess
{
    private static $instance = null;

    private $server;

    private $frame;

    private $data;

    public function __construct($server, $frame)
    {
        $this->server = $server;
        $this->frame = $frame;
        $this->data = json_decode($this->frame->data, true);
    }

    public static function getInstance($server = null, $frame = null)
    {
        if (empty(self::$instance)) {
            self::$instance = new static($server, $frame);
        }
        return self::$instance;
    }

    public static function destroy()
    {
        self::$instance = null;
    }

    public function getServer()
    {
        return $this->server;
    }

    public function getFrame()
    {
        return $this->frame;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getArgs()
    {
        return isset($this->data['arguments']) ?? null;
    }

    public function getModule()
    {
        return empty($this->data['module']) ? strtoupper($this->data['module']) : 'Index';
    }

    public function getMethod()
    {
        return empty($this->data['method']) ? strtoupper($this->data['method']) : 'Index';
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->server, $method], $params);
    }

    public function pushToClient($data)
    {
        $this->sendToCllient($this->frame->fd, $data);
    }

    public function sendToClient($fd, $data)
    {
        if (is_string($data)) {
            $this->server->push($fd, $data);
        } elseif (is_array($data)) {
            $this->server->push($fd, json_encode($data));
        }
    }

    public function pushToClients($data)
    {
        foreach ($this->server->connections as $fd) {
            $this->sendToClient($fd, $data);
        }
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]) ? true : false;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

}

