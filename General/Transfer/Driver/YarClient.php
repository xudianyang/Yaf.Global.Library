<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Transfer\Driver;

use Yar_Client;
use General\Crypt\AES;
use General\Transfer\Exception;


class YarClient extends AbstractDriver
{
    /**
     * Yar_Client实例
     *
     * @var \Yar_Client
     */
    protected $_client;

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

    public function __construct($options = null)
    {
        parent::__construct($options);
    }

    /**
     * 初始化，参数检查，URL绑定
     *
     * @throws Exception\RuntimeException
     */
    protected function init()
    {
        // 判断远程调用列表，返回URL
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
        $this->_params['token'] = $this->getToken();

        if (empty($this->_module) || empty($this->_controller) || empty($this->_action)){
            throw new Exception\RuntimeException("Yar Client [Module/Controller/Action] is empty");
        }

        $this->_client = new Yar_Client($url);
        $this->_client->SetOpt(YAR_OPT_CONNECT_TIMEOUT, 2000);
        $this->_client->SetOpt(YAR_OPT_TIMEOUT, 20000);
        $this->_client->SetOpt(YAR_OPT_PACKAGER, $this->_package);
    }

    /**
     *
     *
     * @param $method
     * @param array $params
     * @return mixed|null
     * @throws Exception\RuntimeException
     */
    public function __call($method, $params = array())
    {
        $this->init();

        if (!empty($params) && count($params) > 1) {
            throw new Exception\RuntimeException("Yar Client Params Error ");
        } elseif (!empty($params)) {
            $params = array_merge((array)$this->_params, $params[0]);
        } else {
            $params = $this->_params;
        }

        $result  = call_user_func(array($this->_client, $method), $this->_module, $this->_controller, $this->_action, $params);

        if (!$result) {
            return null;
        }

        $this->_app = null;
        $result = json_decode($result, true);

        if ($result['errno']) {
            $result['errno'] = hexdec($result['errno']);
            throw new Exception\RuntimeException($result['message'], $result['errno']);
        } else {
            return $result['data'];
        }
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
