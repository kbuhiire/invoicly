<?php

namespace Tests\Feature;

use App\Mail\InvoiceReminderMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private function client(User $user, array $attributes = []): Client
    {
        return Client::factory()->for($user)->external()->create(array_merge([
            'email' => 'client@example.com',
            'phone' => '+256700000000',
            'preferred_contact_method' => 'email',
            'whatsapp_opt_in' => false,
        ], $attributes));
    }

    private function openInvoice(User $user, Client $client, int $dueInDays, string $amount = '100.00'): Invoice
    {
        return Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'currency' => 'UGX',
            'amount' => $amount,
            'amount_paid' => '0',
            'issue_date' => now()->subDays(28)->toDateString(),
            'due_date' => now()->addDays($dueInDays)->toDateString(),
        ]);
    }

    private function service(): ReminderService
    {
        return app(ReminderService::class);
    }

    public function test_sends_upcoming_email_for_invoice_due_soon(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user);
        $invoice = $this->openInvoice($user, $client, 2); // within default 3-day lead

        $tally = $this->service()->sendForUser($user);

        $this->assertSame(1, $tally['sent']);
        Mail::assertQueued(InvoiceReminderMail::class, 1);

        $log = ReminderLog::sole();
        $this->assertSame('upcoming', $log->type);
        $this->assertSame('email', $log->channel);
        $this->assertSame(ReminderLog::STATUS_SENT, $log->status);
        $this->assertNotNull($invoice->refresh()->last_reminder_sent_at);
    }

    public function test_reminder_is_deduped_across_runs(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user);
        $this->openInvoice($user, $client, 2);

        $this->service()->sendForUser($user);
        $second = $this->service()->sendForUser($user);

        $this->assertSame(0, $second['sent']);
        Mail::assertQueued(InvoiceReminderMail::class, 1);
        $this->assertSame(1, ReminderLog::count());
    }

    public function test_sends_overdue_reminder(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user);
        $this->openInvoice($user, $client, -10); // due 10 days ago

        $this->service()->sendForUser($user);

        $log = ReminderLog::sole();
        $this->assertSame('overdue', $log->type);
        $this->assertSame(ReminderLog::STATUS_SENT, $log->status);
    }

    public function test_too_early_invoice_is_not_reminded(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user);
        $this->openInvoice($user, $client, 20); // well outside the lead window

        $tally = $this->service()->sendForUser($user);

        $this->assertSame(0, $tally['sent']);
        Mail::assertNotQueued(InvoiceReminderMail::class);
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_paid_invoice_is_not_reminded(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user);
        Invoice::factory()->for($user)->for($client)->paid()->create([
            'currency' => 'UGX',
            'amount' => '100.00',
            'amount_paid' => '100.00',
            'issue_date' => now()->subDays(28)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->service()->sendForUser($user);

        Mail::assertNotQueued(InvoiceReminderMail::class);
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_falls_back_to_email_when_whatsapp_not_opted_in(): void
    {
        Mail::fake();
        Http::fake();
        config()->set('invoicly.reminders.channels', ['whatsapp', 'email']);
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.token', 'secret');
        config()->set('services.twilio.whatsapp_from', '+15551234567');
        config()->set('services.twilio.content_sids.upcoming', 'HXupcoming');

        $user = User::factory()->create();
        $client = $this->client($user, ['whatsapp_opt_in' => false]);
        $this->openInvoice($user, $client, 2);

        $this->service()->sendForUser($user);

        Mail::assertQueued(InvoiceReminderMail::class, 1);
        Http::assertNothingSent(); // WhatsApp was skipped (not opted in)
        $this->assertSame('email', ReminderLog::sole()->channel);
    }

    public function test_sends_sms_via_twilio(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);
        config()->set('invoicly.reminders.channels', ['sms']);
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.token', 'secret');
        config()->set('services.twilio.from', '+15557654321');

        $user = User::factory()->create();
        $client = $this->client($user, ['email' => null, 'preferred_contact_method' => 'sms']);
        $this->openInvoice($user, $client, 2);

        $this->service()->sendForUser($user);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/Messages.json')
                && $request['To'] === '+256700000000'
                && $request['From'] === '+15557654321'
                && filled($request['Body']);
        });

        $log = ReminderLog::sole();
        $this->assertSame('sms', $log->channel);
        $this->assertSame(ReminderLog::STATUS_SENT, $log->status);
        $this->assertSame('SM123', $log->provider_message_sid);
    }

    public function test_sends_whatsapp_with_content_template(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SMwa', 'status' => 'queued'], 201)]);
        config()->set('invoicly.reminders.channels', ['whatsapp']);
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.token', 'secret');
        config()->set('services.twilio.whatsapp_from', '+15551234567');
        config()->set('services.twilio.content_sids.upcoming', 'HXupcoming');

        $user = User::factory()->create();
        $client = $this->client($user, ['whatsapp_opt_in' => true, 'preferred_contact_method' => 'whatsapp']);
        $this->openInvoice($user, $client, 2);

        $this->service()->sendForUser($user);

        Http::assertSent(function ($request) {
            return $request['From'] === 'whatsapp:+15551234567'
                && $request['To'] === 'whatsapp:+256700000000'
                && $request['ContentSid'] === 'HXupcoming'
                && filled($request['ContentVariables']);
        });

        $this->assertSame('whatsapp', ReminderLog::sole()->channel);
    }

    public function test_sends_via_slack(): void
    {
        Http::fake(['slack.com/*' => Http::response(['ok' => true, 'ts' => '167.89'], 200)]);
        config()->set('invoicly.reminders.channels', ['slack']);
        config()->set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
        config()->set('services.slack.notifications.channel', '#billing');

        $user = User::factory()->create();
        $client = $this->client($user, ['email' => null, 'phone' => null, 'preferred_contact_method' => 'slack']);
        $this->openInvoice($user, $client, 2);

        $this->service()->sendForUser($user);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage')
            && $request['channel'] === '#billing');

        $log = ReminderLog::sole();
        $this->assertSame('slack', $log->channel);
        $this->assertSame('167.89', $log->provider_message_sid);
    }

    public function test_skipped_when_no_channel_can_reach_client(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user, ['email' => null]);
        $this->openInvoice($user, $client, 2);

        $tally = $this->service()->sendForUser($user);

        $this->assertSame(1, $tally['skipped']);
        Mail::assertNotQueued(InvoiceReminderMail::class);
        $log = ReminderLog::sole();
        $this->assertSame(ReminderLog::STATUS_SKIPPED, $log->status);
        $this->assertNull($log->channel);
    }

    public function test_late_payer_is_reminded_earlier_than_a_reliable_client(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();

        // Both invoices are due in 6 days — beyond the base 3-day lead but within
        // the late-payer window (3 + 4). Only the flagged client should be nudged.
        $latePayer = $this->client($user, ['flagged_for_review' => true]);
        $reliable = $this->client($user, ['flagged_for_review' => false]);

        $this->openInvoice($user, $latePayer, 6);
        $this->openInvoice($user, $reliable, 6);

        $this->service()->sendForUser($user);

        Mail::assertQueued(InvoiceReminderMail::class, 1);
        $this->assertSame(1, ReminderLog::where('client_id', $latePayer->id)->count());
        $this->assertSame(0, ReminderLog::where('client_id', $reliable->id)->count());
    }

    public function test_overdue_reminders_stop_after_the_cap(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);
        config()->set('invoicly.reminders.overdue_interval_days', 7);
        config()->set('invoicly.reminders.max_overdue_reminders', 4);

        $user = User::factory()->create();
        $client = $this->client($user);
        // Due ~200 days ago -> overdue iteration far past the cap.
        $this->openInvoice($user, $client, -200);

        $tally = $this->service()->sendForUser($user);

        $this->assertSame(0, $tally['sent']);
        Mail::assertNotQueued(InvoiceReminderMail::class);
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_command_runs(): void
    {
        Mail::fake();
        config()->set('invoicly.reminders.channels', ['email']);

        $user = User::factory()->create();
        $client = $this->client($user);
        $this->openInvoice($user, $client, 2);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertQueued(InvoiceReminderMail::class, 1);
    }
}
