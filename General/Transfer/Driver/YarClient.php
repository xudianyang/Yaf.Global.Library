<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Transfer\Driver;

use Yaf\Application;
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
        $allows = Application::app()->getConfig()->application->yar->allows;
        $names =  explode(',',$allows['name']);
        $urls = explode(',', $allows['url']);

        // 本地调用与远程调用，本地调用、远程调用验证Token（验证机制相同）todo 远程调用验证机制
        if (empty($this->_app) || ucfirst($this->_app) == APP_NAME) {
            $url = APP_URL.$this->_entry;
        } elseif (in_array(ucfirst($this->_app), $names)) {
            $url = $urls[array_search(ucfirst($this->_app), $names)].$this->_entry;
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
        return json_decode($result, true);
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
