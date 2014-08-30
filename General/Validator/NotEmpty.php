<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Validator;

class NotEmpty extends AbstractValidator
{
    public function isValid($value)
    {
        return empty($value) === false;
    }
}