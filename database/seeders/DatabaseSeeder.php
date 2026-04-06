<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@invoicly.test',
            'preferred_currency' => 'UGX',
        ]);

        $externalClients = [
            'TAAGSOLUTIONS GmbH',
            'Acme Ltd',
            'Northwind Traders',
        ];

        $clients = collect($externalClients)->map(fn(string $name) => Client::factory()
            ->for($user)
            ->external()
            ->create(['name' => $name]));

        Client::factory()->for($user)->invoicly()->count(2)->create();

        $statuses = [
            InvoiceStatus::Paid,
            InvoiceStatus::Paid,
            InvoiceStatus::Paid,
            InvoiceStatus::AwaitingPayment,
            InvoiceStatus::AwaitingPayment,
        ];

        for ($i = 0; $i < 15; $i++) {
            DB::transaction(function () use ($user, $clients, $statuses, $i) {
                $issueDate = now()->subDays(random_int(5, 120));

                $invoice = new Invoice([
                    'issue_date' => $issueDate,
                    'status' => $statuses[$i % count($statuses)],
                    'currency' => 'UGX',
                    'amount' => fake()->randomFloat(2, 500_000, 50_000_000),
                    'amount_secondary' => fake()->randomFloat(2, 100, 15_000),
                    'currency_secondary' => 'EUR',
                ]);

                $invoice->user()->associate($user);
                $invoice->client()->associate($clients->random());
                $invoice->number = Invoice::nextNumberForUser(
                    $user,
                    ClientType::External,
                    $invoice->issue_date
                );
                $invoice->save();

                if (random_int(0, 1)) {
                    $relative = 'invoices/attachments/' . $invoice->id . '-seed.txt';
                    Storage::disk('local')->put($relative, 'Attachment placeholder');
                    $invoice->update(['attachment_path' => $relative]);
                }

                InvoiceLineItem::factory()
                    ->count(random_int(1, 3))
                    ->sequence(fn($sequence) => ['sort_order' => $sequence->index])
                    ->for($invoice)
                    ->create();
            });
        }
    }
}
