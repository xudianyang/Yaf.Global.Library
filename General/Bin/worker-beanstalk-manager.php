#!/usr/bin/env /data/server/php/bin/php
<?php

declare(ticks = 1);
$loader = new SplClassLoader();
$loader->register();
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'WorkerBeanstalkManager.php';
$mgr = new WorkerBeanstalkManager();
$mgr->run();