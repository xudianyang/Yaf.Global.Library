#!/usr/bin/env /data/server/php/bin/php
<?php
namespace General\Bin;
use Yaf\Loader as InternalLoader;

declare(ticks = 1);
$loader = InternalLoader::getInstance(null, ini_get('yaf.library'));
spl_autoload_register(array($loader, 'autoload'));

$mgr = new WorkerBeanstalkManager($loader);
$mgr->run();
