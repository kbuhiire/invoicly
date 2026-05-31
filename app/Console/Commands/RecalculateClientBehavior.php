<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ClientBehaviorService;
use Illuminate\Console\Command;

class RecalculateClientBehavior extends Command
{
    protected $signature = 'clients:recalculate-behavior
                            {--user= : Limit to a single user id}';

    protected $description = 'Recompute cached payment-behaviour aggregates and credit scores for clients';

    public function handle(ClientBehaviorService $service): int
    {
        $query = User::query();
        if ($this->option('user')) {
            $query->whereKey($this->option('user'));
        }

        $clients = 0;
        $query->each(function (User $user) use ($service, &$clients) {
            $clients += $service->recalculateForUser($user);
        });

        $this->info("Recomputed behaviour for {$clients} client(s).");

        return self::SUCCESS;
    }
}
