<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Transfer\Driver;

use Yaf\Application;
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
     * 构造方法
     *
     * @param string | null $package
     * @param string | null $entry
     */
    public function __construct($package = null, $entry = null)
    {
        if (!empty($package)) {
            $this->_package = $package;
        }

        if (!empty($entry)) {
            $this->_entry = $entry;
        }
    }

    /**
     * 初始化，参数检查，URL绑定
     *
     * @throws Exception\RuntimeException
     */
    protected function getRouterUrl()
    {
        // 判断远程调用列表，返回URL
        $allows = Application::app()->getConfig()->application->yar->allows;
        $names =  explode(',',$allows['name']);
        $urls = explode(',', $allows['url']);

        // 本地调用与远程调用，本地调用、远程调用验证Token（验证机制相同）todo 远程调用验证机制
        if (empty($this->_app) || ucfirst($this->_app) == APP_NAME) {
            $url = APP_URL.$this->_entry;
        } elseif (in_array(ucfirst($this->_app), $names)) {
            $url = $urls[array_search(ucfirst($this->_app), $names)].$this->_entry;
        } else {
            throw new Exception\RuntimeException("Yar Client app is invalid");
        }

        return $url;
    }

    /**
     * 添加远程接口调用
     *
     * @param array $result 接口返回的结果（按引用传值）
     * @param string $router 路由参数
     * @param array $params 接口请求的参数
     * @param array $options Yar可选项
     * @param string $method 请求的接口方法名
     *
     * @return void
     */
    public function add(&$result, $router, $params = array(), $options = array(), $method = 'api')
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

        $sequence = Yar_Concurrent_Client::call($this->getRouterUrl(), $method, $this->params[$key],
            array($this, 'callback'), array($this, 'errorCallback'), (array)$options
        );
        $this->sequences[$sequence] = & $result;

        $this->_app = null;
    }

    /**
     * 默认的接口请求成功的回调函数
     *
     * @param string $result 接口返回的数据
     * @param array $callInfo 接口调用的相关信息
     *
     * @return void
     */
    public function callBack($result, $callInfo)
    {
        $this->sequences[$callInfo['sequence']] = json_decode($result, true);
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

        $this->params = array();
    }

    /**
     * 获取Token
     *
     * @return string
     */
    public function getToken()
    {
        $aes = new AES();

        $appConfig = Application::app()->getConfig();
        $aes->setKey($appConfig->application->yar->secret);
        $token = base64_encode($aes->encrypt(time() + $appConfig->application->yar->token_expire));

        return $token;
    }
}