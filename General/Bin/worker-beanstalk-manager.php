#!/usr/bin/env /data/server/php/bin/php
<?php
namespace General\Bin;

declare(ticks = 1);
// 初始化自动器
$loader = new \SplClassLoader();
$loader->register();

$mgr = new WorkerBeanstalkManager($loader);
$mgr->run();