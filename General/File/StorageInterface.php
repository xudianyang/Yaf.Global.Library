<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\File;

interface StorageInterface
{
    public function exists($filename);

    public function read($filename);

    public function write($filename, $content);

    public function delete($filename);
}