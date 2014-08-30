<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Db\Table;

interface TableInterface
{
    public function getTable();

    public function select($where = null);

    public function insert($set);

    public function update($set, $where = null);

    public function delete($where);
}
