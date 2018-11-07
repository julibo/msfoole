<?php
namespace Julibo\Msfoole\Config\Driver;

use Symfony\Component\Yaml\Yaml as SymfonyYaml;

class Yml
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function parse()
    {
        $result = [];
        $yamlConfig = SymfonyYaml::parse(file_get_contents($this->config));
        if (!empty($yamlConfig)) {
            if (isset($yamlConfig['environments'])) {
                $result = $yamlConfig['environments'];
            } else {
                $result = $yamlConfig;
            }
        }
        return $result;
    }
}