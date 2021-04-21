<?php
include './vendor/autoload.php';

use Aliyun\Gateway\Subscriber\Http\HttpRequest;
use Aliyun\Gateway\Subscriber\Auth\FactoryAuth;

class OcrInvoiceApi extends HttpRequest
{
    public $method = 'JSON';

    protected $host = 'https://ocrapi-invoice.taobao.com';

    protected $path = '/ocrservice/invoice';

    public function withUrl(string $value)
    {
        $this->setParams('url',$value);
        return $this;
    }

    public function withImg(string $value)
    {
        $this->setParams('img',$value);
        return $this;
    }

}

$invoiceUrl = 'http://pic2.58cdn.com.cn/p1/big/n_v1bkuymczy3x6fmera6bwq.jpg';

//签名认证方式
$appkey = 'xxxxx';
$appsecret = 'xxxxxx';
$obj = new OcrInvoiceApi();
$res = $obj->setSignClient(FactoryAuth::sign($appkey, $appsecret))
    ->withUrl($invoiceUrl)
    ->request();

//appcode身份认证方式
$appcode = 'xxxxx';
$res = $obj->setSignClient(FactoryAuth::appCode($appcode))
    ->withUrl($invoiceUrl)
    ->request();
