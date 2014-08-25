<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

class Regex extends AbstractValidator
{
    /**
     * Regular expression pattern
     *
     * @var string
     */
    protected $pattern;

    public function __construct($pattern)
    {
        if (is_string($pattern)) {
            $this->setPattern($pattern);
            parent::__construct(array());
            return;
        }

        parent::__construct($pattern);
    }

    /**
     * Returns the pattern option
     *
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Sets the pattern option
     *
     * @param  string $pattern
     * @throws Exception\InvalidArgumentException if there is a fatal error in pattern matching
     * @return Regex Provides a fluent interface
     */
    public function setPattern($pattern)
    {
        $this->pattern = (string) $pattern;
        $status = @preg_match($this->pattern, 'Test');
        $error = preg_last_error();

        if (false === $status) {
            throw new Exception\InvalidArgumentException("Internal error parsing the pattern '{$this->pattern}'", 0, $error);
        }

        return $this;
    }

    public function isValid($value)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return false;
        }
        return @preg_match($this->pattern, $value);
    }
}