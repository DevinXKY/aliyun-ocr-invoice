<?php
/**
 * User: xukeyan
 * Date: 2021/4/20
 * Time: 11:30
 */

namespace Aliyun\Gateway\Subscriber\Http;

use Aliyun\Gateway\Subscriber\Http\HttpRequest;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class HttpClient
{
    protected $request;
    public $client;
    public $timeout = 30;
    public $connect_timeout = 30;


    public function __construct(HttpRequest $httpRequest)
    {
        $this->request = $httpRequest;
        $stack = HandlerStack::create();
        $stack->push($this->request->getSignClient());
        $this->client = new Client([
            'base_uri' => $this->request->getHost(),
            'http_errors' => false,
            'handler' => $stack,
        ]);
    }

    public function send($method, $params)
    {
        $params['headers'] = $params['headers'] ?? [];
        $params['headers'] = array_merge($params['headers'], $this->request->getHeaders());
        $params['timeout'] = $this->timeout;
        $params['connect_timeout'] = $this->connect_timeout;
        //todo 请求日志
        try {
            $response = $this->client->request($method, $this->request->getPath(), $params);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
        $code = $response->getStatusCode();
        $body = $response->getBody() ? json_decode($response->getBody(), true) : [];
        if (200 > $code || 300 <= $code) {
            $errmsg = $response->hasHeader('X-Ca-Error-Message') ? $response->getHeaderLine('X-Ca-Error-Message') : $body['error_msg'] ?? '';
            throw new \Exception($errmsg, $code);
        }
        return $body;
    }

    public function post()
    {
        $parasm = ['form_params' => $this->request->getParams()];
        return $this->send('POST', $parasm);
    }

    public function json()
    {
        $parasm = ['json' => $this->request->getParams()];
        return $this->send('POST', $parasm);
    }

    public function get()
    {
        $parasm = ['query' => $this->request->getParams()];
        return $this->send('GET', $parasm);
    }


}