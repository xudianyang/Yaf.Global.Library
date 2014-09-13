<?php

class WorkerReverseClass
{
    public function __construct()
    {

    }

    public function run($payload,  &$log)
    {
        file_put_contents('/vagrant/b.txt', var_export($payload, true));
        return 'success';
    }
}