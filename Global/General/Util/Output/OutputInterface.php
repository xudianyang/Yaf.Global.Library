<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Util\Output;

use General\Util\Sender\SenderInterface;

interface OutputInterface
{
    /**
     * Output the content
     */
    public function __invoke(SenderInterface $sender);

}