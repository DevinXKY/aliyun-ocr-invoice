<?php
/**
 * User: xukeyan
 * Date: 2021/4/20
 * Time: 10:18
 */

namespace Aliyun\Gateway\Subscriber\Http;

use Aliyun\Gateway\Subscriber\Http\HttpClient;

class HttpRequest
{
    protected $host;
    protected $path;
    protected $method;
    protected $headers = array();
    protected $params = array();
    protected $signClient;

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeader($key, $value)
    {
        if (null == $this->headers) {
            $this->headers = array();
        }
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHeader($key)
    {
        return $this->headers[$key];
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParams($key, $value)
    {
        if (null == $this->params) {
            $this->params = array();
        }
        $this->params[$key] = $value;
        return $this;
    }

    public function setSignClient($client)
    {
        $this->signClient = $client;
        return $this;
    }

    public function getSignClient()
    {
        return $this->signClient;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function request()
    {
        return call_user_func([new HttpClient($this), strtolower($this->method)]);
    }

}
