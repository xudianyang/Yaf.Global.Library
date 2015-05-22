<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Mail\Address;

interface AddressInterface
{
    public function getEmail();
    public function getName();
    public function toString();
}
