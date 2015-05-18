<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Mail;

class Address implements Address\AddressInterface
{
    protected $email;
    protected $name;

    /**
     * Constructor
     *
     * @param  string $email
     * @param  null|string $name
     * @throws Exception\InvalidArgumentException
     * @return Address
     */
    public function __construct($email, $name = null)
    {
        if (!is_string($email)) {
            throw new Exception\InvalidArgumentException('Email must be a string');
        }
        if (null !== $name && !is_string($name)) {
            throw new Exception\InvalidArgumentException('Name must be a string');
        }

        $this->email = $email;
        $this->name  = $name;
    }

    /**
     * Retrieve email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Retrieve name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * String representation of address
     *
     * @return string
     */
    public function toString()
    {
        $string = '<' . $this->getEmail() . '>';
        $name   = $this->getName();
        if (null === $name) {
            return $string;
        }

        $string = $name . ' ' . $string;
        return $string;
    }
}
