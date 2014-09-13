<?php

function WorkerReverse($payload, &$log)
{
    file_put_contents('/vagrant/a.txt', var_export($payload, true));
    return 'success';
}