<?php
namespace Julibo\Msfoole\Exception;

class HttpResponseException extends \RuntimeException
{
    /**
     * @var Response
     */
    protected $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
