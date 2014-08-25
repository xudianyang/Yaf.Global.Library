<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Session\SaveHandler;

interface SaveHandlerInterface
{
    /**
     * Open Session - retrieve resources
     *
     * @param string $savePath
     * @param string $name
     */
    public function open($savePath, $name);

    /**
     * Close Session - free resources

     */
    public function close();

    /**
     * Read session data
     *
     * @param string $id
     */
    public function read($id);

    /**
     * Write Session - commit data to resource
     *
     * @param string $id
     * @param mixed $data
     */
    public function write($id, $data);

    /**
     * Destroy Session - remove data from resource for
     * given session id
     *
     * @param string $id
     */
    public function destroy($id);

    /**
     * Garbage Collection - remove old session data older
     * than $maxlifetime (in seconds)
     *
     * @param int $maxlifetime
     */
    public function gc($maxlifetime);
}
