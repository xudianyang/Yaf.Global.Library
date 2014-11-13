<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Transfer\Driver;

use Yar_Concurrent_Client;
use General\Crypt\AES;
use General\Transfer\Exception;


class YarConcurrentClient extends AbstractDriver
{
    /**
     * 打包协议
     *
     * @var string
     */
    protected $_package = 'json';

    /**
     * 接口地址
     *
     * @var string
     */
    protected $_entry = 'api.php';

    /**
     * 多接口返回的结果引用数组
     *
     * @var array
     */
    public $sequences = array();

    /**
     * 请求参数数组
     *
     * @var array
     */
    public $params = array();

    /**
     * 接口密钥
     *
     * @var string
     */
    protected $_secret;

    /**
     * 接口密钥有效时间，单位秒
     *
     * @var int
     */
    protected $_expire;

    /**
     * 允许请求的接口列表
     *
     * @var array
     */
    protected $_allows;

    /**
     * 构造方法
     *
     * @param array $options
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
    }

    /**
     * 初始化，参数检查，URL绑定
     *
     * @throws Exception\RuntimeException
     */
    protected function getRouterUrl()
    {
        // 判断远程调用列表，返回URL
        $allows = $this->_allows;
        $names = array_keys($this->_allows);
        $urls  = array_values($this->_allows);

        // 本地调用与远程调用，本地调用、远程调用验证Token（验证机制相同）todo 远程调用验证机制
        if (empty($this->_app) || ucfirst($this->_app) == APP_NAME) {
            $url = APP_URL.$this->_entry;
        } elseif (in_array(strtolower($this->_app), $names)) {
            $url = $urls[array_search(strtolower($this->_app), $names)].$this->_entry;
            $this->_params['yarsource'] = APP_NAME;
        } else {
            throw new Exception\RuntimeException("Yar Client app is invalid");
        }

        return $url;
    }

    /**
     * 添加远程接口调用
     *
     * @param string $router 路由参数
     * @param array $params 接口请求的参数
     * @param array $result 接口返回的结果（按引用传值）
     * @param array $options Yar可选项
     * @param callable $errorCallBack 错误回调
     * @param string $method 请求的接口方法名
     *
     * @return void
     */
    public function add($router, $params = array(), &$result = null, $options = array(), callable $errorCallBack = null, $method = 'api')
    {
        $this->setOptions($router);

        $key = count($this->params) + 1;
        $params = (array) $params;

        $params['yarsource'] = APP_NAME;
        $params['token']     = $this->getToken();

        $this->params[$key] = array(
            'module'        => $this->_module,
            'controller'    => $this->_controller,
            'action'        => $this->_action,
            'param'         => $params,
        );

        if (!isset($options[YAR_OPT_PACKAGER])) {
            $options[YAR_OPT_PACKAGER] = $this->_package;
        }

        if (!isset($options[YAR_OPT_TIMEOUT])) {
            $options[YAR_OPT_TIMEOUT] = 2000;
        }

        if (!is_callable($errorCallBack)) {
            $callback = array($this, 'errorCallback');
        } else {
            $callback = $errorCallBack;
        }

        $sequence = Yar_Concurrent_Client::call($this->getRouterUrl(), $method, $this->params[$key],
            array($this, 'callback'), $callback, (array)$options
        );

        if (!is_null($result)) {
            $this->sequences[$sequence] = & $result;
        }
        $this->_app = null;
    }

    /**
     * 默认的接口请求成功的回调函数
     *
     * @param string $result 接口返回的数据
     * @param array $callInfo 接口调用的相关信息
     * @throws Exception\RuntimeException
     * @return void
     */
    public function callBack($result, $callInfo)
    {
        if (isset($this->sequences[$callInfo['sequence']])) {
            if (!$result) {
                return null;
            }

            $result = json_decode($result, true);

            if ($result['errno']) {
                $result['errno'] = hexdec($result['errno']);
                throw new Exception\RuntimeException($result['message'], $result['errno']);
            }

            $this->sequences[$callInfo['sequence']] = $result['data'];
        }
    }

    /**
     * 接口请求错误的回调函数
     *
     * @param int $type 错误类型
     * @param string $error 错误信息
     * @param array $callInfo 接口调用的相关信息
     *
     * @throws \General\Transfer\Exception\RuntimeException
     *
     * @return void
     */
    public function errorCallBack($type, $error, $callInfo)
    {
        throw new Exception\RuntimeException("Yar_Concurrent_Client Call Error Type: ". $type
            . "Error: " . $error . "CallInfo: " . var_export($callInfo, true)
        );
    }

    /**
     * 默认的在所有的接口请求发出的回调函数
     *
     * @return bool
     */
    public function requestCallBack()
    {
        return true;
    }

    /**
     * 并发执行接口请求
     *
     * @param callable | null $callback 在所有的接口请求发出的回调函数
     * @param callable | null $errorCallback 接口请求错误的回调函数
     *
     * @return void
     */
    public function exec($callback = null, $errorCallback = null)
    {
        if (is_callable($callback)) {
            $requestCallBack = $callback;
        } else {
            $requestCallBack = array($this, 'requestCallBack');
        }

        if (is_callable($errorCallback)) {
            $requestErrorCallback = $errorCallback;
        } else {
            $requestErrorCallback = array($this, 'errorCallback');
        }

        Yar_Concurrent_Client::loop($requestCallBack, $requestErrorCallback);

        $this->reset();
    }

    /**
     * 重置接口调用栈
     *
     * @param void
     * @return void
     */
    public function reset()
    {
        Yar_Concurrent_Client::reset();
        $this->sequences = array();
        $this->params = array();
    }

    /**
     * 设置打包协议
     *
     * @param string $package
     * @return void
     */
    public function setPackage($package)
    {
        $this->_package = $package;
    }

    /**
     * 设置请求的实体
     *
     * @param string $entry
     * @return void
     */
    public function setEntry($entry)
    {
        $this->_entry = $entry;
    }

    /**
     * 获取Token
     *
     * @return string
     */
    public function getToken()
    {
        $aes = new AES();
        $aes->setKey($this->_secret);
        $token = base64_encode($aes->encrypt(time() + $this->_expire));

        return $token;
    }

    /**
     * 设置secret
     *
     * @param string $secret
     */
    public function setSecret($secret)
    {
        $this->_secret = $secret;
    }

    /**
     * 设置过期时间expire
     *
     * @param int $expire
     */
    public function setExpire($expire)
    {
        $this->_expire = $expire;
    }

    /**
     * 设置允许请求的接口列表
     */
    public function setAllows($allows)
    {
        $this->_allows = $allows;
    }
}