<?php

namespace App\Console\Commands;

use App\Services\CurrencyRateService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class UpdateCurrencyRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-currency-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update currency rates from exchangerate.host';

    /**
     * Execute the console command.
     */
    public function handle(CurrencyRateService $service): int
    {
        $service->updateRates();
        $this->info('Currency rates updated successfully.');

        return CommandAlias::SUCCESS;
    }
}
