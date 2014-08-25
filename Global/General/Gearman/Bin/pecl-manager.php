#!/usr/bin/env /usr/local/server/php/bin/php
<?php
/**
 * Implements the worker portions of the pecl/gearman library
 *
 * @author      Brian Moon <brian@moonspot.net>
 * @copyright   1997-Present Brian Moon
 * @package     GearmanManager
 *
 */

declare(ticks = 1);

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'GearmanPeclManager.php';

$mgr = new GearmanPeclManager();
$mgr->run();