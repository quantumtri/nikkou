<?php

namespace App\Console\Commands;

use App\Services\ReceiptNotifier;
use Illuminate\Console\Command;

class NotifyOverdueFindings extends Command
{
    protected $signature = 'receipts:notify-overdue';

    protected $description = 'Alert admins about goods-receipt findings past their due date';

    public function handle(ReceiptNotifier $notifier): int
    {
        $count = $notifier->notifyOverdueFindings();
        $this->info("{$count} temuan terlambat diberitahukan.");

        return self::SUCCESS;
    }
}
