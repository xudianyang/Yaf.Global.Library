<?php
/**
 *
 *
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Sql\Predicate;

class IsNull implements PredicateInterface
{

    protected $specification = '%1$s IS NULL';
    protected $identifier;

    /**
     * Constructor
     *
     * @param  string $identifier
     */
    public function __construct($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Get parts for where statement
     *
     * @return array
     */
    public function getExpressionData()
    {
        return array(
            array(
                $this->specification,
                array($this->identifier),
                array(self::TYPE_IDENTIFIER),
            )
        );
    }

}
