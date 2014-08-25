<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

class Domain extends AbstractValidator
{
    public function isValid($value)
    {
        return preg_match('#^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}$#', $value);
    }
}