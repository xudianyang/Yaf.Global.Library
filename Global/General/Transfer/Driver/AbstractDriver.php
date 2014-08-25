<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Transfer\Driver;

use General\Transfer\Exception;

abstract class AbstractDriver implements DriverInterface
{

    protected $_app;

    protected $_module;

    protected $_controller;

    protected $_action;

    protected $_params = array();

    /**
     * @param  array|\Traversable|null $options
     */
    public function __construct($options = null)
    {
        if ($options !== NULL) {
            $this->setOptions($options);
        }
    }

    /**
     * @param  array|\Traversable $options
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setOptions($options)
    {
        if (!is_array($options) && !$options instanceof \Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Parameter provided to %s must be an array or Traversable',
                __METHOD__
            ));
        }

        // 如果存在router，以router优先匹配module,controller,action
        if (isset($options['router']) && !empty($options['router'])) {
            if (strpos($options['router'], '#')) {
                $router = explode('#', $options['router']);
                $options['app'] = $router[0];
                $mvc = explode('/', $router[1]);
            } else {
                $mvc = explode('/', $options['router']);
            }

            $options['module'] = $mvc[0];
            $options['controller'] = $mvc[1];
            $options['action'] = $mvc[2];
            unset($options['router']);
        }

        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
        return $this;
    }

    /**
     *
     *
     * @param string $key
     * @param mixed $value
     * @throws Exception\RuntimeException
     */
    public function setOption($key, $value)
    {
        $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (!method_exists($this, $setter)) {
            throw new Exception\RuntimeException(sprintf(
                'The option "%s" does not have a matching "%s" setter method which must be defined',
                $key, $setter
            ));
        }
        $this->{$setter}($value);
    }

    /**
     * @param $app
     */
    public function setApp($app)
    {
        $this->_app = $app;
    }

    /**
     * @param string $module
     */
    public function setModule($module)
    {
        $this->_module = $module;
    }

    /**
     * @param string $controller
     */
    public function setController($controller)
    {
        $this->_controller = $controller;
    }

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->_action = $action;
    }

    /**
     * @param $params
     */
    public function setParams($params)
    {
        if (is_array($params) && !empty($params)) {
            $this->_params = $params;
        }
    }

    abstract public function getToken();
}