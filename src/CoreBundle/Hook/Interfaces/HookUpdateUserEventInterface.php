<?php

/* For licensing terms, see /license.txt */

/**
 * This file contains all Hook interfaces and their relation.
 * They are used for Hook classes.
 */

namespace Chamilo\CoreBundle\Hook\Interfaces;

/**
 * Interface HookUpdateUserEventInterface.
 */
interface HookUpdateUserEventInterface extends HookEventInterface
{
    /**
     * Update all the observers.
     *
     * @param int $type
     *
     * @return int
     */
    public function notifyUpdateUser($type);
}
