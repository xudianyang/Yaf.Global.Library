<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

use General\Validator\Exception\InvalidArgumentException;
use General\Validator\Exception\RuntimeException;

class ValidatorChain implements \Countable, ValidatorInterface
{
    /**
     * Validator chain
     *
     * @var array
     */
    protected $validators = array();

    /**
     * Validate error message
     *
     * @var array
     */
    protected $messages = array();

    /**
     * @var bool
     */
    protected $breakChainOnFailure = true;

    /**
     * Validator Loader
     *
     * @var ValidatorLoader
     */
    protected $loader = null;

    /**
     * Attach a validator to the end of the chain
     *
     * @param  ValidatorInterface|callable|string        $validator
     * @param  null|string $message
     * @return $this
     */
    public function addValidator($validator, $message = null)
    {
        $this->validators[] = array(
            'instance' => $validator,
            'message' => $message
        );
        return $this;
    }

    /**
     * Adds a validator to the beginning of the chain
     *
     * @param  ValidatorInterface|callable|string          $validator
     * @param  null|string $message
     * @return $this
     */
    public function prependValidator($validator, $message = null)
    {
        array_unshift(
            $this->validators,
            array(
                'instance' => $validator,
                'message' => $message
            )
        );
        return $this;
    }

    /**
     * Merge the validator chain with the one given in parameter
     *
     * @param ValidatorChain $validatorChain
     * @return $this
     */
    public function merge(ValidatorChain $validatorChain)
    {
        foreach ($validatorChain->getValidators() as $validator) {
            $this->validators[] = $validator;
        }
        return $this;
    }

    /**
     * Return the count of attached validators
     *
     * @return int
     */
    public function count()
    {
        return count($this->validators);
    }

    /**
     * Set break chain on failure setting
     *
     * @param  bool $break
     * @return $this
     */
    public function setBreakChainOnFailure($break)
    {
        $this->breakChainOnFailure = (bool) $break;
        return $this;
    }

    /**
     * Get break chain on failure setting
     *
     * @return bool
     */
    public function isBreakChainOnFailure()
    {
        return $this->breakChainOnFailure;
    }

    /**
     * Returns true if and only if $value passes all validations in the chain
     *
     * @param mixed $value
     * @param null|bool $breakChainOnFailure
     * @throws Exception\RuntimeException
     * @throws Exception\InvalidArgumentException
     * @return bool
     */
    public function isValid($value, $breakChainOnFailure = null)
    {
        $this->messages = array();
        $result         = true;
        if ($breakChainOnFailure === null) {
            $breakChainOnFailure = $this->breakChainOnFailure;
        }
        foreach ($this->validators as $element) {
            $validator = $element['instance'];
            $message = $element['message'];

            if (is_callable($validator)) {
                try {
                    if ($validator($value) !== false) {
                        continue;
                    }
                } catch (\Exception $ex) {
                    $message = $message ?: $ex->getMessage();
                }
            } else {
                if (is_string($validator)) {
                    if (preg_match('/^[^a-z0-9_\\\s]/i', $validator)) {
                        // regex
                        $validator = new Regex($validator);
                    } else {
                        // validator name
                        $validator = $this->getLoader()->get($validator);
                        if (!$validator) {
                            throw new Exception\RuntimeException('validator ' . $validator . ' not found in the given path');
                        }
                    }
                }
                if (!$validator instanceof ValidatorInterface) {
                    throw new InvalidArgumentException(
                        'validator instance must be either a ValidatorInterface object or callable object');
                }
                if ($validator->isValid($value)) {
                    continue;
                }
                $message = $message ?: $validator->getMessages();
            }

            $result = false;
            $this->messages[] = $message;
            if ($breakChainOnFailure) {
                break;
            }
        }
        return $result;
    }

    public function getFirstMessage()
    {
        return reset($this->messages);
    }

    /**
     * Returns array of validation failure messages
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Get all the validators
     *
     * @return array
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * Get Validator Loader
     *
     * @return null|ValidatorLoader
     */
    public function getLoader()
    {
        if ($this->loader == null) {
            $this->loader = new ValidatorLoader();
        }
        return $this->loader;
    }

    /**
     * Register a validator lookup path
     *
     * @param $path
     * @param string $namespace
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function registerPath($path, $namespace = ValidatorLoader::NS_ROOT)
    {
        return $this->getLoader()->registerPath($path, $namespace);
    }

    /**
     * Invoke chain as command
     *
     * @param  mixed $value
     * @return bool
     */
    public function __invoke($value)
    {
        return $this->isValid($value);
    }

    /**
     * Prepare validator chain for serialization
     *
     * @return array
     */
    public function __sleep()
    {
        return array('validators', 'messages');
    }

    /**
     * Perform a deep clone
     *
     * @return ValidatorChain A cloned ValidatorChain
     */
    public function __clone()
    {
        if (is_object($this->loader)) {
            $this->loader = clone $this->loader;
        }
    }
}