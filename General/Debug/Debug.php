<?php
/**
 * 调试信报头息通过控制台显示
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Debug;

final class Debug
{
    /**
     * @var null
     */
    static private $_driver = NULL;

    /**
     * @var array
     */
    static private $_headers = array();

    /**
     * @param      $message
     * @param bool $showtime
     */
    static function console($message, $showtime = false)
    {
        static $lasttime = APP_START;

        $thistime = microtime(true);
        $usedtime = $thistime - $lasttime;
        $lasttime = $thistime;

        $label = $showtime ? sprintf("%09.5fs", $usedtime) : NULL;

        if (strstr($_SERVER['HTTP_USER_AGENT'], ' Firefox/')) {
            if (is_null(self::$_driver)) {
                self::$_driver = Driver\FirePHP::getInstance(true);
            }
            self::$_driver->info($message, $label);
        }
    }

    /**
     * 服务端console的方法
     *
     * @access public
     * @param $value
     * @return void
     */
    static public function service($value)
    {
        self::$_headers[] = $value;
    }

    /**
     * 序列化服务端console的数据
     *
     * @access public
     * @return string
     */
    static public function serializeHeaders()
    {
        if (!empty(self::$_headers)) {
            return serialize(self::$_headers);
        }
    }
}