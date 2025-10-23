<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentLink;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireOrdersByPaymentLink extends Command
{
    protected $signature = 'app:orders-expire-by-link {--dry-run : Show what would be changed without saving} {--ids=* : Only check these order IDs}';

    protected $description = 'Mark orders as expired if their payment link is expired or revoked and not paid yet';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_filter(array_map('intval', (array) $this->option('ids')));

        $query = PaymentLink::query()
            ->with('order')
            ->where(function ($q) {
                $q->where('revoked', true)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('expires_at')->where('expires_at', '<', Carbon::now());
                  });
            });

        if (!empty($ids)) {
            $query->whereHas('order', function ($q) use ($ids) {
                $q->whereIn('id', $ids);
            });
        }

        $links = $query->get();

        $count = 0;
        foreach ($links as $link) {
            $order = $link->order;
            if (!$order) continue;
            if ($order->status === OrderStatus::Paid) continue;
            if ($order->status === OrderStatus::Expired) continue;

            $this->info(sprintf('Order #%d marked as expired (link %s).', $order->id, $link->token));
            if (!$dryRun) {
                $order->status = OrderStatus::Expired;
                $order->save();
            }
            $count++;
        }

        $this->info("Processed: {$count} order(s)");
        return Command::SUCCESS;
    }
}


