<?php

namespace App\Console\Commands;

use App\Services\SopService;
use Illuminate\Console\Command;

class RemindSops extends Command
{
    protected $signature = 'sops:remind';

    protected $description = 'Remind staff to read active SOPs and admins to review SOPs that are due';

    public function handle(SopService $sops): int
    {
        $this->info('Pengingat baca: '.$sops->remindUnread().', pengingat tinjauan: '.$sops->remindReviews());

        return self::SUCCESS;
    }
}
