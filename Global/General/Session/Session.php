<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Session;

abstract class Session
{
    /**
     * Check whether or not the session was started
     *
     * @var bool
     */
    protected static $sessionStarted = false;

    /**
     * Whether or not session id cookie has been deleted
     *
     * @var bool
     */
    protected static $sessionCookieDeleted = false;

    /**
     * @var SaveHandler\SaveHandlerInterface
     */
    protected static $saveHandler;

    /**
     * @var string value returned by session_name()
     */
    protected static $name;

    /**
     * Private list of php's ini values for ext/session
     * null values will default to the php.ini value, otherwise
     * the value below will overwrite the default ini value, unless
     * the user has set an option explicity with setOptions()
     *
     * @var array
     */
    protected static $defaultOptions = array(
        'save_path'               => null,
        'name'                    => null, /* this should be set to a unique value for each application */
        'save_handler'            => null,
        //'auto_start'            => null, /* intentionally excluded (see manual) */
        'gc_probability'          => null,
        'gc_divisor'              => null,
        'gc_maxlifetime'          => null,
        'serialize_handler'       => null,
        'cookie_lifetime'         => null,
        'cookie_path'             => null,
        'cookie_domain'           => null,
        'cookie_secure'           => null,
        'cookie_httponly'         => null,
        'use_cookies'             => null,
        'use_only_cookies'        => 'on',
        'referer_check'           => null,
        'entropy_file'            => null,
        'entropy_length'          => null,
        'cache_limiter'           => null,
        'cache_expire'            => null,
        'use_trans_sid'           => null,
        'bug_compat_42'           => null,
        'bug_compat_warn'         => null,
        'hash_function'           => null,
        'hash_bits_per_character' => null
    );

    /**
     * Set options
     *
     * @param array|\Traversable $options
     * @throws Exception\InvalidArgumentException
     */
    public static function setOptions($options)
    {
        if (!is_array($options) && !$options instanceof \Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Parameter provided to %s must be an array or Traversable',
                __METHOD__
            ));
        }

        // set the options the user has requested to set
        foreach ($options as $name => $value) {

            $name = strtolower($name);

            // set the ini based values
            if (array_key_exists($name, self::$defaultOptions)) {
                ini_set("session.$name", $value);
            }
        }
    }

    /**
     * Set session save handler object
     *
     * @param  SaveHandler\SaveHandlerInterface $saveHandler
     */
    public static function setSaveHandler(SaveHandler\SaveHandlerInterface $saveHandler)
    {
        self::$saveHandler = $saveHandler;
    }

    /**
     * Get SaveHandler Object
     *
     * @return SaveHandler\SaveHandlerInterface
     */
    public static function getSaveHandler()
    {
        return self::$saveHandler;
    }


    /**
     * Does a session exist and is it currently active?
     *
     * @return bool
     */
    public static function sessionExists()
    {
        $sid = defined('SID') ? constant('SID') : false;
        if ($sid !== false && self::getId()) {
            return true;
        }
        if (headers_sent()) {
            return true;
        }
        return false;
    }

    /**
     * Start session
     *
     * @param array|\Traversable $options
     */
    public static function start($options = null)
    {
        if (self::sessionExists()) {
            return;
        }

        if ($options) {
            self::setOptions($options);
        }

        if (($saveHandler = self::getSaveHandler())) {
            // register the session handler with ext/session
            self::registerSaveHandler($saveHandler);
        }

        //session_start();
    }

    /**
     * Destroy/end a session
     *
     * @param bool $remove_cookie
     * @return void
     */
    public static function destroy($remove_cookie = true)
    {
        if (!self::sessionExists()) {
            return;
        }

        session_destroy();

        if ($remove_cookie) {
            self::expireSessionCookie();
        }
    }

    /**
     * Write session to save handler and close
     *
     * @return void
     */
    public static function writeClose()
    {
        session_write_close();
    }

    /**
     * Attempt to set the session name
     *
     * @param  string $name
     * @throws Exception\InvalidArgumentException
     */
    public static function setName($name)
    {
        if (self::sessionExists()) {
            throw new Exception\InvalidArgumentException('Cannot set session name after a session has already started');
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new Exception\InvalidArgumentException(
                'Name provided contains invalid characters; must be alphanumeric only'
            );
        }

        self::$name = $name;
        session_name($name);
    }

    /**
     * Get session name
     *
     * @return string
     */
    public static function getName()
    {
        if (null === self::$name) {
            self::$name = session_name();
        }
        return self::$name;
    }

    /**
     * Set session ID
     *
     * @param  string $id
     */
    public static function setId($id)
    {
        if (self::sessionExists()) {
            throw new Exception\RuntimeException(
                'Session has already been started, to change the session ID call regenerateId()'
            );
        }
        session_id($id);
    }

    /**
     * Get session ID
     *
     * @return string
     */
    public static function getId()
    {
        return session_id();
    }

    /**
     * Regenerate id
     *
     * @param  bool $deleteOldSession
     */
    public static function regenerateId($deleteOldSession = true)
    {
        session_regenerate_id((bool)$deleteOldSession);
    }

    /**
     * Expire the session cookie
     *
     * @return void
     */
    public static function expireSessionCookie()
    {
        if (self::$sessionCookieDeleted) {
            return;
        }

        self::$sessionCookieDeleted = true;

        if (isset($_COOKIE[self::getName()])) {
            $cookie_params = session_get_cookie_params();
            setcookie(
                self::getName(), // session name
                false, // value
                $_SERVER['REQUEST_TIME'] - 42000, // TTL for cookie
                $cookie_params['path'], $cookie_params['domain'], $cookie_params['secure']
            );
        }
    }

    public static function touchSessionCookie()
    {
        if (self::$sessionCookieDeleted) {
            return;
        }

        if (isset($_COOKIE[self::getName()])) {
            $cookie_params = session_get_cookie_params();
            setcookie(
                self::getName(), // session name
                self::getId(), // value
                $_SERVER['REQUEST_TIME'] + (int) $cookie_params['lifetime'], // TTL for cookie
                $cookie_params['path'], $cookie_params['domain'], $cookie_params['secure']
            );
        }
    }

    /**
     * Register Save Handler with ext/session
     *
     * @param SaveHandler\SaveHandlerInterface $saveHandler
     * @return bool
     */
    protected static function registerSaveHandler(SaveHandler\SaveHandlerInterface $saveHandler)
    {
        return session_set_save_handler(
            array($saveHandler, 'open'),
            array($saveHandler, 'close'),
            array($saveHandler, 'read'),
            array($saveHandler, 'write'),
            array($saveHandler, 'destroy'),
            array($saveHandler, 'gc')
        );
    }
}