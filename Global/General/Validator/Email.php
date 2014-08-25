<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

class Email extends AbstractValidator
{
    public function isValid($value)
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}