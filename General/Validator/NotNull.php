<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

class NotNull extends AbstractValidator
{
    public function isValid($value)
    {
        return null !== $value;
    }
}