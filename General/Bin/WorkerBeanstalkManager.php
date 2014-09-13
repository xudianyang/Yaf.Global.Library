<?php
namespace General\Bin;
declare(ticks = 1);
use General\Pheanstalk\Pheanstalk;

class WorkerBeanstalkManager extends WorkerManager {

    /**
     * Starts a worker for the PECL library
     *
     * @param   array   $worker_list    List of worker functions to add
     * @param   array   $timeouts       list of worker timeouts to pass to server
     * @return  void
     *
     */
    protected function start_lib_worker($worker_list, $timeouts = array()) {
        $host = strtok($this->servers[0], ':');
        $thisWorker = new Pheanstalk($host, strtok(':'), 5);
        foreach($worker_list as $w){
            $timeout = (isset($timeouts[$w]) ? $timeouts[$w] : null);
            if (!is_null($timeouts)) {
                $this->log("Reserve With Timeout $w ; timeout: " . $timeout, WorkerManager::LOG_LEVEL_WORKER_INFO);
            }
            $this->log("Watching Tube $w", WorkerManager::LOG_LEVEL_WORKER_INFO);
            $thisWorker->watch($w);
        }

        $start = time();

        while(!$this->stop_work){
            if (isset($timeouts[$w]) && $timeouts[$w]) {
                $job = $thisWorker->reserve($timeouts[$w]);
            } else {
                $job = $thisWorker->reserve();
            }

            if (!$job) {
                sleep(5);
            } else {
                $this->do_job($job, $thisWorker);
            }

            /**
             * Check the running time of the current child. If it has
             * been too long, stop working.
             */
            if($this->max_run_time > 0 && time() - $start > $this->max_run_time) {
                $this->log("Been running too long, exiting", WorkerManager::LOG_LEVEL_WORKER_INFO);
                $this->stop_work = true;
            }

            if(!empty($this->config["max_runs_per_worker"]) && $this->job_execution_count >= $this->config["max_runs_per_worker"]) {
                $this->log("Ran $this->job_execution_count jobs which is over the maximum({$this->config['max_runs_per_worker']}), exiting", WorkerManager::LOG_LEVEL_WORKER_INFO);
                $this->stop_work = true;
            }
        }

        $thisWorker->getConnection()->getSocket()->close();
    }

    /**
     * Wrapper function handler for all registered functions
     * This allows us to do some nice logging when jobs are started/finished
     */
    public function do_job(\General\Pheanstalk\Job $job, Pheanstalk $thisWorker) {

        static $objects;

        if($objects===null) $objects = array();

        $w = json_decode($job->getData(), true);

        $h = $job->getId();

        $statsJob = $thisWorker->statsJob($job);
        $func = $statsJob['tube'];

        if (empty($w['payload'])) {
            $funcName = $func;
            $this->log("Function {$funcName}'s Payload is empty, Id ($h) Deleted");
            $thisWorker->delete($job);
            return;
        }

        $job_name = $func;

        if($this->prefix){
            $func = $this->prefix.$job_name;
        } else {
            $func = $job_name;
        }

        if(empty($objects[$job_name]) && !function_exists($func) && !class_exists($func, false)){

            if(!isset($this->functions[$job_name])){
                $this->log("Function $func is not a registered job name, Id ($h) Deleted");
                $thisWorker->delete($job);
                return;
            }

            require_once $this->functions[$job_name]["path"];

            if(class_exists($func, false) && method_exists($func, "run")){

                $this->log("Creating a $func object", WorkerManager::LOG_LEVEL_WORKER_INFO);
                $objects[$job_name] = new $func();

            } elseif(!function_exists($func)) {

                $this->log("Function $func not found, Id ($h) Deleted");
                $thisWorker->delete($job);
                return;
            }

        }

        $this->log("($h) Starting Job: $job_name", WorkerManager::LOG_LEVEL_WORKER_INFO);

        $this->log("($h) Workload: " . var_export($w, true), WorkerManager::LOG_LEVEL_DEBUG);

        $log = array();

        /**
         * Run the real function here
         */
        if(isset($objects[$job_name])){
            $this->log("($h) Calling object for $job_name.", WorkerManager::LOG_LEVEL_DEBUG);
            $result = $objects[$job_name]->run($w['payload'], $log);
        } elseif(function_exists($func)) {
            $this->log("($h) Calling function for $job_name.", WorkerManager::LOG_LEVEL_DEBUG);
            $result = $func($w['payload'], $log);
        } else {
            $this->log("($h) FAILED to find a function or class for $job_name.", WorkerManager::LOG_LEVEL_INFO);
        }

        if(!empty($log)){
            foreach($log as $l){

                if(!is_scalar($l)){
                    $l = explode("\n", trim(print_r($l, true)));
                } elseif(strlen($l) > 256){
                    $l = substr($l, 0, 256)."...(truncated)";
                }

                if(is_array($l)){
                    foreach($l as $ln){
                        $this->log("($h) $ln", WorkerManager::LOG_LEVEL_WORKER_INFO);
                    }
                } else {
                    $this->log("($h) $l", WorkerManager::LOG_LEVEL_WORKER_INFO);
                }

            }
        }

        $result_log = $result;

        if(!is_scalar($result_log)){
            $result_log = explode("\n", trim(print_r($result_log, true)));
        } elseif(strlen($result_log) > 256){
            $result_log = substr($result_log, 0, 256)."...(truncated)";
        }

        if(is_array($result_log)){
            foreach($result_log as $ln){
                $this->log("($h) $ln", WorkerManager::LOG_LEVEL_DEBUG);
            }
        } else {
            $this->log("($h) $result_log", WorkerManager::LOG_LEVEL_DEBUG);
        }

        $type = gettype($result);
        settype($result, $type);


        $this->job_execution_count++;

        if (!isset($w['command'])) {
            $this->log("Finish Job, Id ($h) Deleted");
            $thisWorker->delete($job);
        } else if (in_array($w['command'], array('bury', 'release', 'delete'))) {
            switch ($w['command']) {
                case 'bury':
                    if (isset($w['args']) && count($w['args']) >= 1) {
                        $this->log("Finish Job, Id ($h) buried pri ({$w['args'][0]})");
                        $thisWorker->bury($job, $w['args'][0]);
                    } else {
                        $this->log("Finish Job, Id ($h) buried");
                        $thisWorker->bury($job);
                    }
                    break;
                case 'release':
                    if (isset($w['args']) && count($w['args']) == 1) {
                        $this->log("Finish Job, Id ($h) released pri ({$w['args'][0]})");
                        $thisWorker->release($job, $w['args'][0]);
                    } else if (!isset($w['args'])) {
                        $this->log("Finish Job, Id ($h) released");
                        $thisWorker->release($job);
                    } else {
                        $this->log("Finish Job, Id ($h) released pri ({$w['args'][0]}) delay ({$w['args'][1]})");
                        $thisWorker->release($job, $w['args'][0], $w['args'][1]);
                    }
                    break;
                case 'delete':
                    $this->log("Finish Job, Id ($h) Deleted");
                    $thisWorker->delete($job);
                    break;
            }
        } else {
            $this->log("Finish Job, Id ($h) Deleted");
            $thisWorker->delete($job);
        }

        return $result;

    }

    /**
     * Validates the PECL compatible worker files/functions
     */
    protected function validate_lib_workers() {

        foreach($this->functions as $func => $props){
            require_once $props["path"];
            $real_func = $this->prefix.$func;
            if(!function_exists($real_func) &&
                (!class_exists($real_func) || !method_exists($real_func, "run"))){
                $this->log("Function $real_func not found in ".$props["path"]);
                posix_kill($this->pid, SIGUSR2);
                exit();
            }
        }

    }

}