<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mail\RecurringInvoiceGenerated;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\RecurringInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GenerateRecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_generates_single_invoice_and_advances_from_scheduled_run_date(): void
    {
        Mail::fake();

        $now = Carbon::parse('2026-04-25 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $template = Invoice::factory()->for($user)->for($client)->create([
            'number' => 'EINV-2026-1',
            'issue_date' => '2026-01-01',
            'status' => InvoiceStatus::AwaitingPayment,
            'amount' => '100.00',
            'is_template' => true,
        ]);
        InvoiceLineItem::factory()->for($template)->create([
            'description' => 'Retainer',
            'quantity' => 1,
            'unit_price' => '100.00',
            'sort_order' => 0,
        ]);

        $schedule = RecurringInvoice::query()->create([
            'user_id' => $user->id,
            'template_invoice_id' => $template->id,
            'name' => 'Weekly test',
            'frequency' => 'weekly',
            'next_run_at' => '2026-04-01',
            'is_active' => true,
        ]);

        $this->artisan('invoices:generate-recurring')->assertSuccessful();

        $this->assertSame(2, Invoice::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, Invoice::query()->where('user_id', $user->id)->where('is_template', false)->count());

        $generated = Invoice::query()->where('user_id', $user->id)->where('is_template', false)->first();
        $this->assertNotNull($generated);
        $this->assertSame('2026-04-01', $generated->issue_date->format('Y-m-d'));

        $schedule->refresh();
        $this->assertSame('2026-04-08', $schedule->next_run_at->format('Y-m-d'));
        $this->assertSame('2026-04-01', $schedule->last_run_at->format('Y-m-d'));

        Mail::assertQueued(RecurringInvoiceGenerated::class, 1);

        Carbon::setTestNow();
    }

    public function test_catch_up_generates_all_overdue_periods(): void
    {
        Mail::fake();

        $now = Carbon::parse('2026-04-25 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $template = Invoice::factory()->for($user)->for($client)->create([
            'number' => 'EINV-2026-1',
            'issue_date' => '2026-01-01',
            'status' => InvoiceStatus::AwaitingPayment,
            'amount' => '100.00',
            'is_template' => true,
        ]);
        InvoiceLineItem::factory()->for($template)->create([
            'description' => 'Retainer',
            'quantity' => 1,
            'unit_price' => '100.00',
            'sort_order' => 0,
        ]);

        RecurringInvoice::query()->create([
            'user_id' => $user->id,
            'template_invoice_id' => $template->id,
            'name' => 'Weekly test',
            'frequency' => 'weekly',
            'next_run_at' => '2026-04-01',
            'is_active' => true,
        ]);

        $this->artisan('invoices:generate-recurring', ['--catch-up' => true])->assertSuccessful();

        $generatedCount = Invoice::query()->where('user_id', $user->id)->where('is_template', false)->count();
        $this->assertSame(4, $generatedCount);

        $schedule = RecurringInvoice::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-04-29', $schedule->next_run_at->format('Y-m-d'));
        $this->assertSame('2026-04-22', $schedule->last_run_at->format('Y-m-d'));

        Mail::assertQueued(RecurringInvoiceGenerated::class, 4);

        Carbon::setTestNow();
    }
}
