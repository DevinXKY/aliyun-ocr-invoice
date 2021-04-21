<?php
/**
 * User: xukeyan
 * Date: 2021/4/20
 * Time: 10:47
 */

namespace Aliyun\Gateway\Subscriber\Auth;

use Psr\Http\Message\RequestInterface;

class SignAuth
{
    const HMAC_SHA256 = "HmacSHA256";
    //换行符
    const LF = "\n";
    //分隔符1
    const SPE1 = ",";
    //分隔符2
    const SPE2 = ":";
    //参与签名的系统Header前缀,只有指定前缀的Header才会参与到签名中
    const CA_HEADER_TO_SIGN_PREFIX_SYSTEM = "X-Ca-";
    //请求Header Accept
    const HTTP_HEADER_ACCEPT = "Accept";
    //默认请求Header Accept类型
    const DEFAULT_CONTENT_ACCEPT = "application/text; charset=UTF-8";
    //请求Body内容MD5 Header
    const HTTP_HEADER_CONTENT_MD5 = "Content-MD5";
    //请求Header Content-Type
    const HTTP_HEADER_CONTENT_TYPE = "Content-Type";
    //请求Header Date
    const HTTP_HEADER_DATE = "Date";
    //签名Header
    const X_CA_SIGNATURE = "X-Ca-Signature";
    //所有参与签名的Header
    const X_CA_SIGNATURE_HEADERS = "X-Ca-Signature-Headers";
    //请求时间戳
    const X_CA_TIMESTAMP = "X-Ca-Timestamp";
    //请求放重放Nonce,15分钟内保持唯一,建议使用UUID
    const X_CA_NONCE = "X-Ca-Nonce";
    //APP KEY
    const X_CA_KEY = "X-Ca-Key";


    protected $appkey;
    protected $appsecret;

    public function __construct($appkey, $appsecret)
    {
        $this->appkey = $appkey;
        $this->appsecret = $appsecret;
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
     * @return RequestInterface|static
     */
    private function sign(RequestInterface $request)
    {
        date_default_timezone_set('PRC');
        $headers = $request->getHeaders();
        $headers[self::X_CA_TIMESTAMP] = strval(time() * 1000);
        //防重放，协议层不能进行重试，否则会报NONCE被使用；如果需要协议层重试，请注释此行
        $headers[self::X_CA_NONCE] = strval(self::NewGuid());
        $headers[self::X_CA_KEY] = $this->appkey;
        $headers[self::X_CA_SIGNATURE] = $this->genSignature($request, $headers);
        foreach ($headers as $name => $val) {
            $request = $request->withHeader($name, $val);
        }
        return $request;
    }

    public function genSignature(RequestInterface $request, &$headers)
    {
        $signStr = self::BuildStringToSign(
            $request->getMethod(),
            $request->getUri()->getPath(),
            $headers,
            $this->getParams($request)
        );
        $signStr = base64_encode(hash_hmac('sha256', $signStr, $this->appsecret, true));
        return $signStr;
    }

    public function getParams(RequestInterface $request)
    {
        if ($request->getMethod() == 'POST') {
            $contents = $request->getBody()->getContents();
            if (false === json_decode($contents)) {
                parse_str($contents, $params);
            } else {
                $params = ['' => $contents];
            }
        } else {
            $params = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());
        }
        return $params;
    }


    /**
     * 构建待签名path+(header+query+body)
     */
    private static function BuildStringToSign($method, $path, &$headers, $params)
    {
        if (empty($headers[self::HTTP_HEADER_ACCEPT])) {
            $headers[self::HTTP_HEADER_ACCEPT] = [self::DEFAULT_CONTENT_ACCEPT];
        }
        $sb = "";
        $sb .= strtoupper($method);
        $sb .= self::LF;
        if (array_key_exists(self::HTTP_HEADER_ACCEPT, $headers) && null != $headers[self::HTTP_HEADER_ACCEPT]) {
            $sb .= $headers[self::HTTP_HEADER_ACCEPT][0];
        }
        $sb .= self::LF;
        if (array_key_exists(self::HTTP_HEADER_CONTENT_MD5, $headers) && null != $headers[self::HTTP_HEADER_ACCEPT]) {
            $sb .= $headers[self::HTTP_HEADER_CONTENT_MD5][0];
        }
        $sb .= self::LF;
        if (array_key_exists(self::HTTP_HEADER_CONTENT_TYPE, $headers) && null != $headers[self::HTTP_HEADER_ACCEPT]) {
            $sb .= $headers[self::HTTP_HEADER_CONTENT_TYPE][0];
        }
        $sb .= self::LF;
        if (array_key_exists(self::HTTP_HEADER_DATE, $headers) && null != $headers[self::HTTP_HEADER_ACCEPT]) {
            $sb .= $headers[self::HTTP_HEADER_DATE][0];
        }
        $sb .= self::LF;
        $sb .= self::BuildHeaders($headers, []);
        $sb .= self::BuildResource($path, $params);

        return $sb;
    }

    /**
     * 构建待签名Path+Query+FormParams
     */
    private static function BuildResource($path, $params)
    {
        $sb = "";
        if (0 < strlen($path)) {
            $sb .= $path;
        }
        $sbParam = "";
        $sortParams = array();

        if (is_array($params)) {
            foreach ($params as $itemKey => $itemValue) {
                if (0 < strlen($itemKey)) {
                    $sortParams[$itemKey] = $itemValue;
                }
            }
        }

        //排序
        ksort($sortParams);
        //参数Key
        foreach ($sortParams as $itemKey => $itemValue) {
            if (0 < strlen($itemKey)) {
                if (0 < strlen($sbParam)) {
                    $sbParam .= "&";
                }
                $sbParam .= $itemKey;
                if (null != $itemValue) {
                    if (0 < strlen($itemValue)) {
                        $sbParam .= "=";
                        $sbParam .= $itemValue;
                    }
                }
            }
        }
        if (0 < strlen($sbParam)) {
            $sb .= "?";
            $sb .= $sbParam;
        }
        return $sb;
    }

    /**
     * 构建待签名Http头
     *
     * @param headers              请求中所有的Http头
     * @param signHeaderPrefixList 自定义参与签名Header前缀
     * @return 待签名Http头
     */
    private static function BuildHeaders(&$headers, $signHeaderPrefixList)
    {
        $sb = "";

        if (null != $signHeaderPrefixList) {
            //剔除X-Ca-Signature/X-Ca-Signature-Headers/Accept/Content-MD5/Content-Type/Date
            unset($signHeaderPrefixList[self::X_CA_SIGNATURE]);
            unset($signHeaderPrefixList[self::HTTP_HEADER_ACCEPT]);
            unset($signHeaderPrefixList[self::HTTP_HEADER_CONTENT_MD5]);
            unset($signHeaderPrefixList[self::HTTP_HEADER_CONTENT_TYPE]);
            unset($signHeaderPrefixList[self::HTTP_HEADER_DATE]);
            ksort($signHeaderPrefixList);

            if (is_array($headers)) {
                ksort($headers);
                $signHeadersStringBuilder = "";
                foreach ($headers as $itemKey => $itemValue) {
                    if (self::IsHeaderToSign($itemKey, $signHeaderPrefixList)) {
                        $sb .= $itemKey;
                        $sb .= self::SPE2;
                        if (0 < strlen($itemValue)) {
                            $sb .= $itemValue;
                        }
                        $sb .= self::LF;
                        if (0 < strlen($signHeadersStringBuilder)) {
                            $signHeadersStringBuilder .= self::SPE1;
                        }
                        $signHeadersStringBuilder .= $itemKey;
                    }
                }
                $headers[self::X_CA_SIGNATURE_HEADERS] = $signHeadersStringBuilder;
            }

        }

        return $sb;
    }

    /**
     * Http头是否参与签名
     * return
     */
    private static function IsHeaderToSign($headerName, $signHeaderPrefixList)
    {
        if (NULL == $headerName) {
            return false;
        }
        if (0 == strlen($headerName)) {
            return false;
        }
        if (1 == strpos("$" . $headerName, self::CA_HEADER_TO_SIGN_PREFIX_SYSTEM)) {
            return true;
        }
        if (!is_array($signHeaderPrefixList) || empty($signHeaderPrefixList)) {
            return false;
        }
        if (array_key_exists($headerName, $signHeaderPrefixList)) {
            return true;
        }

        return false;
    }

    private static function NewGuid()
    {
        mt_srand((double)microtime() * 10000);
        $uuid = strtoupper(md5(uniqid(rand(), true)));
        return $uuid;
    }
}