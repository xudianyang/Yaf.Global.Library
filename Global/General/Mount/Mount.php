<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Mount;

class Mount
{

    /**
     * Mount单例
     *
     * @var self
     */
    protected static $instance;

    /**
     * 存储对象单例
     *
     * @var array
     */
    protected $instances = array();

    /**
     * 存储对象实例化过程
     *
     * @var array
     */
    protected $factories = array();

    /**
     * 构造函数
     */
    protected function __construct() {  }

    /**
     * 实例化Mount类
     *
     * @return self
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     *
     *
     * @param string $name
     * @param string|callable|object $factory
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function mount($name, $factory)
    {
        if (!is_string($factory) && !is_callable($factory) && !is_object($factory)) {
            throw new Exception\InvalidArgumentException(
                'Provided factory must be a string class name or an callable or instance object.'
            );
        }

        $name = static::normalizeName($name);

        if ($this->has($name)) {
            $this->unmount($name);
        }

        if (!is_callable($factory) && is_object($factory)) {
            $this->instances[$name] = $factory;
        } else {
            $this->factories[$name] = $factory;
        }

        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function unmount($name)
    {
        $name = static::normalizeName($name);

        unset($this->factories[$name]);

        unset($this->instances[$name]);

        return $this;
    }

    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        $name = static::normalizeName($name);
        return (isset($this->factories[$name]) || isset($this->instances[$name]));
    }

    /**
     * @param $name
     * @return mixed|null
     * @throws Exception\RuntimeException
     */
    public function get($name)
    {
        $name = static::normalizeName($name);

        if (func_num_args() > 1) {
            $params = func_get_args();
            array_shift($params);
            $nameCheck = $name . ':' . md5(serialize($params));

            if (isset($this->instances[$nameCheck])) {
                return $this->instances[$nameCheck];
            }
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (!isset($this->factories[$name])) {
            return null;
        }

        $factory = $this->factories[$name];
        $instance = null;
        if (is_string($factory) && class_exists($factory, true)) {
            $instance = new $factory;
        } elseif (is_callable($factory)) {
            try {
                if (func_num_args() > 1) {
                    $params = func_get_args();
                    array_shift($params);
                    $name = $name . ':' . md5(serialize($params));
                    $instance = call_user_func_array($factory, $params);
                } else {
                    $instance = $factory($this);
                }

            } catch (\Exception $e) {
                throw new Exception\RuntimeException(
                    sprintf('An exception was raised while creating "%s"', $name), $e->getCode(), $e
                );
            }
        }

        if (!$instance) {
            throw new Exception\RuntimeException('The factory was called but did not return an instance.');
        }
        $this->instances[$name] = $instance;

        return $instance;
    }

    protected static function normalizeName($name)
    {
        return strtolower(str_replace(array(' ', '-', '_', '\\', '/'), '', $name));
    }
}