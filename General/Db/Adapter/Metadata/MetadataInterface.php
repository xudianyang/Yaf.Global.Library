<?php
/**
 *
 *
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Adapter\Metadata;

interface MetadataInterface
{
    /**
     * @param string $table
     * @param string $schema
     * @return array
     */
    public function getColumns($table, $schema = null);

    /**
     * @param string $table
     * @param string $schema
     * @return array
     */
    public function getPrimarys($table, $schema = null);
}
