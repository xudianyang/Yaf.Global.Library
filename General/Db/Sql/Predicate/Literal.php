<?php
/**
 *
 *
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Sql\Predicate;

class Literal implements PredicateInterface
{
    protected $literal = '';

    public function __construct($literal)
    {
        $this->literal = $literal;
    }

    /**
     * @return array
     */
    public function getExpressionData()
    {
        return array(
            array(
                str_replace('%', '%%', $this->literal),
                array(),
                array()
            )
        );
    }
}
