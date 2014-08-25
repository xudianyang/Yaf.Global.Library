<?php

namespace General\Psr\Log;

use Seaslog;
class Seaslogger extends AbstractLogger
{
    public $seaslog;

    public function  __construct()
    {
        $this->seaslog = new Seaslog();
    }

    public function setBasePath($basePath)
    {
        $this->seaslog->setBasePath($basePath);
        return $this;
    }

    public function setLogger($module)
    {
        $this->seaslog->setLogger($module);
        return $this;
    }

    public function getBasePath()
    {
        return $this->seaslog->getBasePath();
    }

    public function getLastLogger()
    {
        return $this->seaslog->getLastLogger();
    }

    public function debug($message, array $content = array())
    {
        $this->seaslog->debug($message, $content);
        return $this;
    }

    public function info($message, array $content = array())
    {
        $this->seaslog->info($message, $content);
        return $this;
    }

    public function notice($message, array $content = array())
    {
        $this->seaslog->notice($message, $content);
        return $this;
    }

    public function warning($message, array $content = array())
    {
        $this->seaslog->warning($message, $content);
    }

    public function error($message, array $content = array())
    {
        $this->seaslog->error($message, $content);
        return $this;
    }

    public function critical($message, array $content = array())
    {
        $this->seaslog->critical($message, $content);
        return $this;
    }

    public function alert($message, array $content = array())
    {
        $this->seaslog->alert($message, $content);
        return $this;
    }

    public function emergency($message, array $content = array())
    {
        $this->seaslog->emergency($message, $content);
        return $this;
    }

    public function log($level, $message, array $context = array()) {
        $this->seaslog->{$level}($message, $context);
        return $this;
    }
}