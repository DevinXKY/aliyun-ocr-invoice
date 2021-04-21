<?php

namespace Aliyun\Gateway\Subscriber\Auth;

use Psr\Http\Message\RequestInterface;

class SimpleAuth
{
    const HEADER_AUTH = 'Authorization';

    protected $appcode;

    public function __construct($appcode)
    {
        $this->appcode = $appcode;
    }

    /**
     * @param callable $handler
     * @return Closure
     */
    public function __invoke(callable $handler)
    {
        return function ($request, array $options) use ($handler) {
            $request = $this->sign($request);
            return $handler($request, $options);
        };
    }

    /**
     * @param RequestInterface $request
     * @return static
     */
    private function sign(RequestInterface $request)
    {
        return $request->withHeader(self::HEADER_AUTH, 'APPCODE ' . $this->appcode);
    }

}