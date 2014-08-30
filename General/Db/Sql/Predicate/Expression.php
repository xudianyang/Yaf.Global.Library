<?php
/**
 *
 *
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Sql\Predicate;

use General\Db\Sql\Expression as BaseExpression;

class Expression extends BaseExpression implements PredicateInterface
{

    /**
     * Constructor
     *
     * @param string $expression
     * @param int|float|bool|string|array $valueParameter
     */
    public function __construct($expression = null, $valueParameter = null /*[, $valueParameter, ... ]*/)
    {
        if ($expression) {
            $this->setExpression($expression);
        }

        if (is_array($valueParameter)) {
            $this->setParameters($valueParameter);
        } else {
            $argNum = func_num_args();
            if ($argNum > 2 || is_scalar($valueParameter)) {
                $parameters = array();
                for ($i = 1; $i < $argNum; $i++) {
                    $parameters[] = func_get_arg($i);
                }
                $this->setParameters($parameters);
            }
        }
    }

}
