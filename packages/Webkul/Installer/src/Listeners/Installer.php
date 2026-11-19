<?php

namespace Webkul\Installer\Listeners;

use Webkul\User\Repositories\UserRepository;

class Installer
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected UserRepository $userRepository) {}

    /**
     * After AzimioCustomers is successfully installed.
     *
     * The upstream Krayin listener reported the installation (domain, admin
     * email and name) to updates.krayincrm.com — removed for AzimioCustomers.
     *
     * @return void
     */
    public function installed()
    {
        //
    }
}
