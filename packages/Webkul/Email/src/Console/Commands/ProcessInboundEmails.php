<?php

namespace Webkul\Email\Console\Commands;

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;

class ProcessInboundEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbound-emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will process the incoming emails from the mail server.';

    /**
     * Create a new command instance.
     *
     * The processor is resolved per tenant inside handle() — it connects to
     * the IMAP server on construction using the tenant-scoped configuration.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handle.
     *
     * @return void
     */
    public function handle()
    {
        $manager = app(TenantManager::class);

        /**
         * IMAP credentials live in the tenant-scoped core_config, so each
         * active tenant's inbox is processed inside its own tenant context —
         * fetched emails are stamped and scoped to that tenant.
         */
        Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->each(function ($tenant) use ($manager) {
            $manager->runAs($tenant, function () use ($tenant) {
                if (! core()->getConfigData('email.imap.account.host')) {
                    return;
                }

                $this->info("Processing the incoming emails for tenant [{$tenant->slug}].");

                try {
                    app(InboundEmailProcessor::class)->processMessagesFromAllFolders();
                } catch (\Exception $e) {
                    $this->error("Tenant [{$tenant->slug}]: ".$e->getMessage());
                }
            });
        });

        $this->info('Incoming emails processed successfully.');
    }
}
