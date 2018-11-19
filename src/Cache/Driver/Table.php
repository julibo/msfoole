<?php
namespace Julibo\Msfoole\Cache\Driver;

use Julibo\Msfoole\Cache\Driver;
use think\Container;

class Table extends Driver
{
    protected $options = [
        'expire' => 0,
        'prefix' => '',
        'serialize' => true
    ];

    public function __construct($options = [])
    {
        $this->handler = Container::get('cachetable');
    }

    public function set($name, $value, $expire = null)
    {
        $this->writeTime++;

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

    }

}