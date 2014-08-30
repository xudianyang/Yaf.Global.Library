<?php
/**
 *
 *
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Sql\Predicate;

class IsNotNull extends IsNull
{
    protected $specification = '%1$s IS NOT NULL';
}
