<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace General\Mail\Protocol;

/**
 * Plugin manager implementation for SMTP extensions.
 *
 * Enforces that SMTP extensions retrieved are instances of Smtp. Additionally,
 * it registers a number of default extensions available.
 */
class SmtpPluginManager
{
    /**
     * Lookup for canonicalized names.
     *
     * @var array
     */
    protected $canonicalNames = array();

    /**
     * @var array
     */
    protected $initializers = array();

    /**
     * Whether or not to share by default
     *
     * @var bool
     */
    protected $shareByDefault = true;

    /**
     * @var array map of characters to be replaced through strtr
     */
    protected $canonicalNamesReplacements = array('-' => '', '_' => '', ' ' => '', '\\' => '', '/' => '');

    /**
     * Default set of extensions
     *
     * @var array
     */
    protected $invokableClasses = array(
        'crammd5' => 'General\Mail\Protocol\Smtp\Auth\Crammd5',
        'login'   => 'General\Mail\Protocol\Smtp\Auth\Login',
        'plain'   => 'General\Mail\Protocol\Smtp\Auth\Plain',
        'smtp'    => 'General\Mail\Protocol\Smtp',
    );

    /**
     * @var integer
     */
    protected $nestedContextCounter = -1;


    /**
     * @var bool
     */
    protected $retrieveFromPeeringManagerFirst = false;

    /**
     * Options to use when creating an instance
     *
     * @var mixed
     */
    protected $creationOptions = null;

    /**
     * Registered services and cached values
     *
     * @var array
     */
    protected $instances = array();

    /**
     * @var string|callable|\Closure|FactoryInterface[]
     */
    protected $factories = array();

    /**
     * @var array
     */
    protected $aliases = array();

    /**
     * @var array
     */
    protected $shared = array();

    /**
     * Validate the plugin
     *
     * Checks that the extension loaded is an instance of Smtp.
     *
     * @param  mixed $plugin
     * @return void
     * @throws Exception\InvalidArgumentException if invalid
     */
    public function validatePlugin($plugin)
    {
        if ($plugin instanceof Smtp) {
            // we're okay
            return;
        }

        throw new Exception\InvalidArgumentException(sprintf(
            'Plugin of type %s is invalid; must extend %s\Smtp',
            (is_object($plugin) ? get_class($plugin) : gettype($plugin)),
            __NAMESPACE__
        ));
    }

    /**
     * Determine if we have an alias
     *
     * @param  string $alias
     * @return bool
     */
    public function hasAlias($alias)
    {
        return isset($this->aliases[$this->canonicalizeName($alias)]);
    }

    /**
     * Retrieve a registered instance
     *
     * @param  string  $name
     * @param  bool    $usePeeringServiceManagers
     * @throws Exception\ServiceNotFoundException
     * @return object|array
     */
    public function getInstance($name, $usePeeringServiceManagers = true)
    {
        // inlined code from ServiceManager::canonicalizeName for performance
        if (isset($this->canonicalNames[$name])) {
            $cName = $this->canonicalNames[$name];
        } else {
            $cName = $this->canonicalizeName($name);
        }

        $isAlias = false;

        if ($this->hasAlias($cName)) {
            $isAlias = true;
            $cName = $this->resolveAlias($cName);
        }

        $instance = null;

        if ($usePeeringServiceManagers && $this->retrieveFromPeeringManagerFirst) {
            $instance = $this->retrieveFromPeeringManager($name);

            if (null !== $instance) {
                return $instance;
            }
        }

        if (isset($this->instances[$cName])) {
            return $this->instances[$cName];
        }

        if (!$instance) {
            $this->checkNestedContextStart($cName);
            if (
                isset($this->invokableClasses[$cName])
                || isset($this->factories[$cName])
                || isset($this->aliases[$cName])
                || $this->canCreateFromAbstractFactory($cName, $name)
            ) {
                $instance = $this->create(array($cName, $name));
            } elseif ($isAlias && $this->canCreateFromAbstractFactory($name, $cName)) {
                /*
                 * case of an alias leading to an abstract factory :
                 * 'my-alias' => 'my-abstract-defined-service'
                 *     $name = 'my-alias'
                 *     $cName = 'my-abstract-defined-service'
                 */
                $instance = $this->create(array($name, $cName));
            } elseif ($usePeeringServiceManagers && !$this->retrieveFromPeeringManagerFirst) {
                $instance = $this->retrieveFromPeeringManager($name);
            }
            $this->checkNestedContextStop();
        }

        // Still no instance? raise an exception
        if ($instance === null) {
            $this->checkNestedContextStop(true);
            if ($isAlias) {
                throw new Exception\ServiceNotFoundException(sprintf(
                    'An alias "%s" was requested but no service could be found.',
                    $name
                ));
            }

            throw new Exception\ServiceNotFoundException(sprintf(
                '%s was unable to fetch or create an instance for %s',
                get_class($this) . '::' . __FUNCTION__,
                $name
            ));
        }

        if (
            ($this->shareByDefault && !isset($this->shared[$cName]))
            || (isset($this->shared[$cName]) && $this->shared[$cName] === true)
        ) {
            $this->instances[$cName] = $instance;
        }

        return $instance;
    }

    /**
     * Retrieve a service from the manager by name
     *
     * Allows passing an array of options to use when creating the instance.
     * createFromInvokable() will use these and pass them to the instance
     * constructor if not null and a non-empty array.
     *
     * @param  string $name
     * @param  array  $options
     * @param  bool   $usePeeringServiceManagers
     *
     * @return object
     *
     * @throws Exception\ServiceNotFoundException
     * @throws Exception\ServiceNotCreatedException
     * @throws Exception\RuntimeException
     */
    public function get($name, $options = array(), $usePeeringServiceManagers = true)
    {
        $isAutoInvokable = false;

        // Allow specifying a class name directly; registers as an invokable class
        if (!$this->has($name) && $this->autoAddInvokableClass && class_exists($name)) {
            $isAutoInvokable = true;

            $this->setInvokableClass($name, $name);
        }

        $this->creationOptions = $options;

        try {
            // instance the connection class
            $instance = $this->getInstance($name, $usePeeringServiceManagers);
        } catch (Exception\ServiceNotFoundException $exception) {
            $this->tryThrowingServiceLocatorUsageException($name, $isAutoInvokable, $exception);
        } catch (Exception\ServiceNotCreatedException $exception) {
            $this->tryThrowingServiceLocatorUsageException($name, $isAutoInvokable, $exception);
        }

        $this->creationOptions = null;

        try {
            $this->validatePlugin($instance);
        } catch (Exception\RuntimeException $exception) {
            $this->tryThrowingServiceLocatorUsageException($name, $isAutoInvokable, $exception);
        }

        return $instance;
    }
    /**
     * Create an instance of the requested service
     *
     * @param  string|array $name
     *
     * @return bool|object
     */
    public function create($name)
    {
        if (is_array($name)) {
            list($cName, $rName) = $name;
        } else {
            $rName = $name;

            // inlined code from ServiceManager::canonicalizeName for performance
            if (isset($this->canonicalNames[$rName])) {
                $cName = $this->canonicalNames[$name];
            } else {
                $cName = $this->canonicalizeName($name);
            }
        }

        if (isset($this->delegators[$cName])) {
            return $this->createDelegatorFromFactory($cName, $rName);
        }

        return $this->doCreate($rName, $cName);
    }

    /**
     * Actually creates the service
     *
     * @param string $rName real service name
     * @param string $cName canonicalized service name
     *
     * @return bool|mixed|null|object
     * @throws Exception\ServiceNotFoundException
     *
     * @internal this method is internal because of PHP 5.3 compatibility - do not explicitly use it
     */
    public function doCreate($rName, $cName)
    {
        $instance = null;

        if (isset($this->factories[$cName])) {
            $instance = $this->createFromFactory($cName, $rName);
        }

        if ($instance === null && isset($this->invokableClasses[$cName])) {
            $instance = $this->createFromInvokable($cName, $rName);
        }
        $this->checkNestedContextStart($cName);
        if ($instance === null && $this->canCreateFromAbstractFactory($cName, $rName)) {
            $instance = $this->createFromAbstractFactory($cName, $rName);
        }
        $this->checkNestedContextStop();

        if ($instance === null && $this->throwExceptionInCreate) {
            $this->checkNestedContextStop(true);
            throw new Exception\ServiceNotFoundException(sprintf(
                'No valid instance was found for %s%s',
                $cName,
                ($rName ? '(alias: ' . $rName . ')' : '')
            ));
        }

        // Do not call initializers if we do not have an instance
        if ($instance === null) {
            return $instance;
        }

        foreach ($this->initializers as $initializer) {
            if ($initializer instanceof InitializerInterface) {
                $initializer->initialize($instance, $this);
            } else {
                call_user_func($initializer, $instance, $this);
            }
        }

        return $instance;
    }

    /**
     * Determine if we can create an instance.
     * Proxies to has()
     *
     * @param  string|array $name
     * @param  bool         $checkAbstractFactories
     * @return bool
     * @deprecated this method is being deprecated as of zendframework 2.3, and may be removed in future major versions
     */
    public function canCreate($name, $checkAbstractFactories = true)
    {
        trigger_error(sprintf('%s is deprecated; please use %s::has', __METHOD__, __CLASS__), E_USER_DEPRECATED);
        return $this->has($name, $checkAbstractFactories, false);
    }
    /**
     * Attempt to create an instance via an invokable class
     *
     * @param  string $canonicalName
     * @param  string $requestedName
     * @return null|\stdClass
     * @throws Exception\ServiceNotFoundException If resolved class does not exist
     */
    protected function createFromInvokable($canonicalName, $requestedName)
    {
        $invokable = $this->invokableClasses[$canonicalName];
        if (!class_exists($invokable)) {
            throw new Exception\ServiceNotFoundException(sprintf(
                '%s: failed retrieving "%s%s" via invokable class "%s"; class does not exist',
                get_class($this) . '::' . __FUNCTION__,
                $canonicalName,
                ($requestedName ? '(alias: ' . $requestedName . ')' : ''),
                $invokable
            ));
        }
        $instance = new $invokable;
        return $instance;
    }

    /**
     * Attempt to create an instance via a factory
     *
     * @param  string $canonicalName
     * @param  string $requestedName
     * @return mixed
     * @throws Exception\ServiceNotCreatedException If factory is not callable
     */
    protected function createFromFactory($canonicalName, $requestedName)
    {
        $factory = $this->factories[$canonicalName];
        if (is_string($factory) && class_exists($factory, true)) {
            $factory = new $factory;
            $this->factories[$canonicalName] = $factory;
        }
        if ($factory instanceof FactoryInterface) {
            $instance = $this->createServiceViaCallback(array($factory, 'createService'), $canonicalName, $requestedName);
        } elseif (is_callable($factory)) {
            $instance = $this->createServiceViaCallback($factory, $canonicalName, $requestedName);
        } else {
            throw new Exception\ServiceNotCreatedException(sprintf(
                'While attempting to create %s%s an invalid factory was registered for this instance type.',
                $canonicalName,
                ($requestedName ? '(alias: ' . $requestedName . ')' : '')
            ));
        }
        return $instance;
    }

    /**
     * Attempt to create an instance via an abstract factory
     *
     * @param  string $canonicalName
     * @param  string $requestedName
     * @return object|null
     * @throws Exception\ServiceNotCreatedException If abstract factory is not callable
     */
    protected function createFromAbstractFactory($canonicalName, $requestedName)
    {
        if (isset($this->nestedContext[$canonicalName])) {
            $abstractFactory = $this->nestedContext[$canonicalName];
            $pendingKey = get_class($abstractFactory).$canonicalName;
            try {
                $this->pendingAbstractFactoryRequests[$pendingKey] = true;
                $instance = $this->createServiceViaCallback(
                    array($abstractFactory, 'createServiceWithName'),
                    $canonicalName,
                    $requestedName
                );
                unset($this->pendingAbstractFactoryRequests[$pendingKey]);
                return $instance;
            } catch (\Exception $e) {
                unset($this->pendingAbstractFactoryRequests[$pendingKey]);
                $this->checkNestedContextStop(true);
                throw new Exception\ServiceNotCreatedException(
                    sprintf(
                        'An abstract factory could not create an instance of %s%s.',
                        $canonicalName,
                        ($requestedName ? '(alias: ' . $requestedName . ')' : '')
                    ),
                    $e->getCode(),
                    $e
                );
            }
        }
        return;
    }

    /**
     * Determine if an instance exists.
     *
     * @param  string|array  $name  An array argument accepts exactly two values.
     *                              Example: array('canonicalName', 'requestName')
     * @param  bool          $checkAbstractFactories
     * @param  bool          $usePeeringServiceManagers
     * @return bool
     */
    public function has($name, $checkAbstractFactories = true, $usePeeringServiceManagers = true)
    {
        if (is_string($name)) {
            $rName = $name;

            // inlined code from ServiceManager::canonicalizeName for performance
            if (isset($this->canonicalNames[$rName])) {
                $cName = $this->canonicalNames[$rName];
            } else {
                $cName = $this->canonicalizeName($name);
            }
        } elseif (is_array($name) && count($name) >= 2) {
            list($cName, $rName) = $name;
        } else {
            return false;
        }

        if (isset($this->invokableClasses[$cName])
            || isset($this->factories[$cName])
            || isset($this->aliases[$cName])
            || isset($this->instances[$cName])
            || ($checkAbstractFactories && $this->canCreateFromAbstractFactory($cName, $rName))
        ) {
            return true;
        }

        if ($usePeeringServiceManagers) {
            $caller = $this->serviceManagerCaller;
            foreach ($this->peeringServiceManagers as $peeringServiceManager) {
                // ignore peering service manager if they are the same instance
                if ($caller === $peeringServiceManager) {
                    continue;
                }

                $peeringServiceManager->serviceManagerCaller = $this;

                if ($peeringServiceManager->has($name)) {
                    $peeringServiceManager->serviceManagerCaller = null;
                    return true;
                }

                $peeringServiceManager->serviceManagerCaller = null;
            }
        }

        return false;
    }
    /**
     * Canonicalize name
     *
     * @param  string $name
     * @return string
     */
    public function canonicalizeName($name)
    {
        if (isset($this->canonicalNames[$name])) {
            return $this->canonicalNames[$name];
        }

        // this is just for performance instead of using str_replace
        return $this->canonicalNames[$name] = strtolower(strtr($name, $this->canonicalNamesReplacements));
    }
    /**
     * Determine if we can create an instance from an abstract factory.
     *
     * @param  string $cName
     * @param  string $rName
     * @return bool
     */
    public function canCreateFromAbstractFactory($cName, $rName)
    {
        if (array_key_exists($cName, $this->nestedContext)) {
            $context = $this->nestedContext[$cName];
            if ($context === false) {
                return false;
            } elseif (is_object($context)) {
                return !isset($this->pendingAbstractFactoryRequests[get_class($context).$cName]);
            }
        }
        $this->checkNestedContextStart($cName);
        // check abstract factories
        $result = false;
        $this->nestedContext[$cName] = false;
        foreach ($this->abstractFactories as $abstractFactory) {
            $pendingKey = get_class($abstractFactory).$cName;
            if (isset($this->pendingAbstractFactoryRequests[$pendingKey])) {
                $result = false;
                break;
            }

            if ($abstractFactory->canCreateServiceWithName($this, $cName, $rName)) {
                $this->nestedContext[$cName] = $abstractFactory;
                $result = true;
                break;
            }
        }
        $this->checkNestedContextStop();
        return $result;
    }

    /**
     *
     * @param string $cName
     * @return self
     */
    protected function checkNestedContextStart($cName)
    {
        if ($this->nestedContextCounter === -1 || !isset($this->nestedContext[$cName])) {
            $this->nestedContext[$cName] = null;
        }
        $this->nestedContextCounter++;
        return $this;
    }
    /**
     *
     * @param bool $force
     * @return self
     */
    protected function checkNestedContextStop($force = false)
    {
        if ($force) {
            $this->nestedContextCounter = -1;
            $this->nestedContext = array();
            return $this;
        }

        $this->nestedContextCounter--;
        if ($this->nestedContextCounter === -1) {
            $this->nestedContext = array();
        }
        return $this;
    }

    /**
     * Attempt to retrieve an instance via a peering manager
     *
     * @param  string $name
     * @return mixed
     */
    protected function retrieveFromPeeringManager($name)
    {
        if (null !== ($service = $this->loopPeeringServiceManagers($name))) {
            return $service;
        }

        $name = $this->canonicalizeName($name);

        if ($this->hasAlias($name)) {
            do {
                $name = $this->aliases[$name];
            } while ($this->hasAlias($name));
        }

        if (null !== ($service = $this->loopPeeringServiceManagers($name))) {
            return $service;
        }

        return;
    }


}
