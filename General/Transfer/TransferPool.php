<?php
/**
 * 远程调用对象池
 *
 * @author xudianyang<admin@phpboy.net>
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Transfer;

abstract class TransferPool
{

    /**
     * 默认Transfer Driver
     */
    const DEFAULT_DRIVER = 'YarClient';

    /**
     * Transfer 存储实例
     * @var array
     */
    protected static $pool = array();

    /**
     * @param string $driver
     * @return Driver\DriverInterface|null
     * @throws Exception\RuntimeException
     */
    public static function factory($config = array(), $driver = self::DEFAULT_DRIVER)
    {
        if (!isset($driver)) {
            $driver = self::DEFAULT_DRIVER;
        }

        $driverName = __NAMESPACE__ . '\\Driver\\' . self::normalizeName($driver);

        if (self::has($driverName)) {
            $instance = self::get($driverName);
        } else {
            if (!class_exists($driverName, true)) {
                throw new Exception\RuntimeException("Driver class '$driverName' not found");
            }

            if (!is_subclass_of($driverName, __NAMESPACE__ . '\\Driver\\DriverInterface')) {
                throw new Exception\RuntimeException("Driver '$driverName' is not implements StorageInterface");
            }

            $instance = new $driverName($config);
            self::register($instance, $driverName);
        }

        return $instance;
    }

    /**
     *
     *
     * @param Driver\DriverInterface $driver
     * @param string $name
     */
    public static function register(Driver\DriverInterface $driver, $name = self::DEFAULT_DRIVER)
    {
        self::$pool[$name] = $driver;
    }

    /**
     *
     *
     * @param string $name
     * @return bool
     */
    public static function has($name)
    {
        return isset(self::$pool[$name]);
    }

    /**
     *
     *
     * @param string $name
     * @return null|Driver\DriverInterface
     */
    public static function get($name = self::DEFAULT_DRIVER)
    {
        return self::has($name) ? self::$pool[$name] : null;
    }

    /**
     *
     *
     * @param $name
     * @return mixed
     */
    protected static function normalizeName($name)
    {
        $name = str_replace(array('-', '_', '.'), ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }
}