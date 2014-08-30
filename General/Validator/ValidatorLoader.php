<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

use Traversable;

class ValidatorLoader
{
    const NS_SEPARATOR = '\\';

    const NS_ROOT = '\\';

    /**
     * @var array
     */
    protected $paths = array();

    public function __construct()
    {
        $this->registerPath(__DIR__, __NAMESPACE__);
    }

    /**
     * Register a path of Validator
     *
     * @param $path string|array|Traversable
     * @param $namespace string
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function registerPath($path, $namespace = self::NS_ROOT)
    {
        if (is_array($path) || $path instanceof Traversable) {
            foreach ($path as $p => $ns) {
                if (is_string($p)) {
                    $this->registerPath($p, $ns ? : $namespace);
                } else {
                    $this->registerPath($ns, $namespace);
                }
            }
            return $this;
        }
        $path = self::normalizePath($path);
        if (!is_dir($path)) {
            throw new Exception\InvalidArgumentException(sprintf('"%s" is not a directory', $path));
        }
        $this->paths[$path] = rtrim($namespace, self::NS_SEPARATOR) . self::NS_SEPARATOR;

        return $this;
    }

    /**
     * Get Validator instance
     *
     * @param string $name
     * @return null|AbstractValidator
     */
    public function get($name)
    {
        $name = static::normalizeName($name);

        if (!($class = $this->getClassFromPath($name))) {
            return null;
        }

        return new $class;
    }

    /**
     * Get valid Validator class name
     *
     * @param $name
     * @return null|string
     */
    public function getClassFromPath($name)
    {
        foreach ($this->paths as $path => $namespace) {
            $filename = static::transformClassNameToFilename($name, $path);
            $class = $namespace . $name;

            if (class_exists($class, false)) {
                if (is_subclass_of($class, __NAMESPACE__ . '\\AbstractValidator')) {
                    return $class;
                }
                continue;
            }

            if (is_file($filename)) {
                include $filename;
                if (class_exists($class, false)
                    && is_subclass_of($class, __NAMESPACE__ . '\\AbstractValidator'))
                {
                    return $class;
                }
            }
        }

        return null;
    }

    protected static function normalizeName($name)
    {
        $name = str_replace(array('.', '-', '_'), ' ', $name);
        $name = str_replace(' ', '', ucwords($name));
        return $name;
    }

    protected static function transformClassNameToFilename($class, $path)
    {
        return $path . str_replace('\\', '/', $class) . '.php';
    }

    protected static function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        if ($path[strlen($path) - 1] != '/') {
            $path .= '/';
        }
        return $path;
    }
}