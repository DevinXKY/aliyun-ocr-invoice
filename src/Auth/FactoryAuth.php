<?php
/**
 * User: xukeyan
 * Date: 2021/4/20
 * Time: 11:05
 */

namespace Aliyun\Gateway\Subscriber\Auth;
use Aliyun\Gateway\Subscriber\Auth\SimpleAuth;
use Aliyun\Gateway\Subscriber\Auth\SignAuth;

class FactoryAuth
{
    public function __construct()
    {
    }

    public static function appCode($code)
    {
        return new SimpleAuth($code);
    }

    public static function sign($appkey,$appsecret)
    {
        return new SignAuth($appkey,$appsecret);
    }
}