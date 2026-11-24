<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Console;

use App\Models\Tenant;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Modules\Licensing\Domain\Orders;

class OverdueOrders extends Command
{
    protected $signature = 'projektrack:licensing:check';

    protected $description = 'Compare license orders with their invoices and tell the managers';

    public function handle(TenantContext $context): int
    {
        foreach (Tenant::query()->get() as $tenant) {
            $context->run($tenant, fn (): int => app(TenantModules::class)->isEnabled('licensing') ? app(Orders::class)->check() : 0);
        }

        return self::SUCCESS;
    }
}
