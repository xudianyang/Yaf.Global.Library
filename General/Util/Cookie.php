<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Util;

final class Cookie
{
    private $prefix = '';

    private $path = '/';

    private $domain = '';

    private static $_instance;

    public function __clone()
    {
        trigger_error('Clone is not allow!',E_USER_ERROR);
    }

    public static function getInstance($options)
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self;
            self::$_instance->setOptions($options);
        }
        return self::$_instance;
    }

    public function setOptions($options)
    {
        $this->prefix = $options['prefix'];
        $this->path = $options['path'];
        $this->domain = $options['domain'];
    }

    /**
     * 设置一个cookie值
     *
     * @param string $var
     * @param mixed $value
     * @param int $time 存活时间
     */
    public function set($var, $value = null, $time = 0)
    {
        if (is_null($value)) {
            $time = time() - 3600;
        } elseif ($time > 0 && $time < 31536000) {
            $time += time();
        }

        $s = $_SERVER['SERVER_PORT'] == '443' ? 1 : 0;
        $var = $this->prefix.$var;
        $_COOKIE[$var] = $value;
        if (is_array($value)) {
            foreach($value as $k=>$v) {
                setcookie($var.'['.$k.']', $v, $time, $this->path, $this->domain, $s);
            }
        } else {
            setcookie($var, $value, $time, $this->path, $this->domain, $s);
        }
    }

    /**
     * @param $var
     * @return bool
     */
    public function get($var)
    {
        $var = $this->prefix.$var;
        return isset($_COOKIE[$var]) ? $_COOKIE[$var] : false;
    }
}