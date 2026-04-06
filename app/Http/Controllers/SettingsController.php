<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\UpdateInvoiceSettingsRequest;
use App\Http\Requests\UpdateSettingsAddressRequest;
use App\Http\Requests\UpdateSettingsPersonalRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Http\Requests\UpdateInvoiceAddressRequest;
use App\Http\Requests\UpdateInvoicePhoneRequest;
use App\Support\AddressNormalization;
use App\Support\PdfAsset;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab', 'personal')->toString();
        $tabs = ['personal', 'invoice', 'account', 'verification', 'payment', 'bookkeeping', 'automation'];
        if (! in_array($tab, $tabs, true)) {
            $tab = 'personal';
        }

        $user = $request->user();

        $recurringInvoices = RecurringInvoice::query()
            ->where('user_id', $user->id)
            ->with(['templateInvoice.client'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RecurringInvoice $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'frequency' => $r->frequency,
                'next_run_at' => $r->next_run_at?->format('Y-m-d'),
                'last_run_at' => $r->last_run_at?->format('Y-m-d'),
                'is_active' => $r->is_active,
                'template_invoice' => $r->templateInvoice ? [
                    'id' => $r->templateInvoice->id,
                    'number' => $r->templateInvoice->number,
                    'client_name' => $r->templateInvoice->client?->name ?? '—',
                    'amount' => $r->templateInvoice->amount,
                    'currency' => $r->templateInvoice->currency,
                ] : null,
            ]);

        $templateInvoices = Invoice::query()
            ->where('user_id', $user->id)
            ->with('client')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'is_template' => $inv->is_template,
                'client_name' => $inv->client?->name ?? '—',
                'amount' => $inv->amount,
                'currency' => $inv->currency,
                'issue_date' => $inv->issue_date?->format('Y-m-d'),
            ]);

        return Inertia::render('Settings/Index', [
            'activeTab' => $tab,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'countries' => $this->countriesPayload(),
            'phoneDialOptions' => $this->phoneDialOptionsPayload(),
            'recurringInvoices' => $recurringInvoices,
            'templateInvoices' => $templateInvoices,
        ]);
    }

    public function editPersonal(Request $request): Response
    {
        return Inertia::render('Settings/PersonalEdit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'countries' => $this->countriesPayload(),
            'timezones' => $this->timezonesPayload(),
        ]);
    }

    public function updatePersonal(UpdateSettingsPersonalRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except(['photo']);
        $user->fill($data);

        $first = trim((string) $request->input('legal_first_name', ''));
        $last = trim((string) $request->input('legal_last_name', ''));
        if ($first !== '' || $last !== '') {
            $user->name = trim($first.' '.$last);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('photo')) {
            if ($user->logo_path) {
                Storage::disk('public')->delete($user->logo_path);
            }
            $user->logo_path = $request->file('photo')->store('profile-logos', 'public');
        }

        $user->save();

        return Redirect::route('settings.index', ['tab' => 'personal']);
    }

    public function editAddress(Request $request): Response
    {
        return Inertia::render('Settings/AddressEdit', [
            'countries' => $this->countriesPayload(),
        ]);
    }

    public function updateAddress(UpdateSettingsAddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $personalRaw = $validated['personal_address'] ?? [];
        $postalRaw = $validated['postal_address'] ?? [];

        $personalNorm = AddressNormalization::normalize($personalRaw);
        $postalNorm = AddressNormalization::normalize($postalRaw);

        $user->personal_address = AddressNormalization::nullIfEmpty($personalNorm);
        $user->postal_address = AddressNormalization::nullIfEmpty($postalNorm);

        $this->syncLegacyAddressFields($user);
        $user->save();

        return Redirect::route('settings.index', ['tab' => 'personal']);
    }

    public function updateInvoice(UpdateInvoiceSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except(['signature']);
        $user->fill($data);

        if ($request->hasFile('signature')) {
            if ($user->invoice_signature_path) {
                Storage::disk('public')->delete($user->invoice_signature_path);
            }
            $user->invoice_signature_path = $request->file('signature')->store('invoice-signatures', 'public');
        }

        $user->save();

        return Redirect::route('settings.index', ['tab' => 'invoice']);
    }

    public function updateInvoiceAddress(UpdateInvoiceAddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->invoice_use_personal_address = (bool) $validated['invoice_use_personal_address'];

        if ($user->invoice_use_personal_address) {
            $user->invoice_address = null;
        } else {
            $raw = $validated['invoice_address'] ?? [];
            $norm = AddressNormalization::normalize(is_array($raw) ? $raw : null);
            $user->invoice_address = AddressNormalization::nullIfEmpty($norm);
        }

        $user->save();

        return Redirect::route('settings.index', ['tab' => 'invoice']);
    }

    public function updateInvoicePhone(UpdateInvoicePhoneRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->invoice_use_personal_phone = (bool) $validated['invoice_use_personal_phone'];

        if ($user->invoice_use_personal_phone) {
            $user->invoice_phone_dial_code = null;
            $user->invoice_phone_national = null;
        } else {
            $user->invoice_phone_dial_code = $validated['invoice_phone_dial_code'];
            $user->invoice_phone_national = $validated['invoice_phone_national'];
        }

        $user->save();

        return Redirect::route('settings.index', ['tab' => 'invoice']);
    }

    public function previewInvoicePdf(Request $request)
    {
        $user = $request->user();

        $invoice = new Invoice([
            'number' => 'EINV-'.now()->format('Y').'-99',
            'issue_date' => now(),
            'status' => InvoiceStatus::AwaitingPayment,
            'currency' => $user->preferred_currency ?: 'UGX',
            'amount' => '100.00',
            'amount_secondary' => null,
            'currency_secondary' => null,
        ]);

        $client = new Client(['name' => 'Sample client']);
        $line = new InvoiceLineItem([
            'description' => 'Sample line item',
            'quantity' => 1,
            'unit_price' => '100.00',
            'sort_order' => 0,
        ]);

        $invoice->setRelation('client', $client);
        $invoice->setRelation('lineItems', collect([$line]));
        $invoice->setRelation('user', $user);

        return Pdf::view('pdfs.invoice', [
            'invoice' => $invoice,
            'issuer' => $user,
            'issuerLogoUri' => PdfAsset::dataUriFromPublicPath($user->logo_path),
            'issuerSignatureUri' => PdfAsset::dataUriFromPublicPath($user->invoice_signature_path),
            'isPreview' => true,
        ])
            ->format('a4')
            ->name('invoice-preview.pdf');
    }

    private function syncLegacyAddressFields(User $user): void
    {
        $pa = $user->personal_address;
        if (! is_array($pa)) {
            $user->address = null;
            $user->country = null;

            return;
        }
        $lines = array_filter([
            $pa['line1'] ?? null,
            $pa['line2'] ?? null,
            $pa['city'] ?? null,
            $pa['region'] ?? null,
            $pa['postal_code'] ?? null,
        ], fn ($v) => $v !== null && (string) $v !== '');
        $user->address = $lines !== [] ? implode("\n", $lines) : null;
        $code = $pa['country_code'] ?? null;
        $countries = config('countries');
        $user->country = $code && isset($countries[$code]) ? $countries[$code] : null;
    }

    /**
     * @return list<array{code: string, name: string, dial: string}>
     */
    private function phoneDialOptionsPayload(): array
    {
        $names = config('countries', []);
        $dials = config('country_calling_codes', []);

        $out = [];
        foreach ($names as $code => $name) {
            if (! isset($dials[$code])) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'name' => $name,
                'dial' => '+'.$dials[$code],
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function countriesPayload(): array
    {
        return collect(config('countries'))
            ->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function timezonesPayload(): array
    {
        $ids = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        $out = [];
        foreach ($ids as $id) {
            try {
                $dt = new \DateTimeImmutable('now', new \DateTimeZone($id));
                $parts = explode('/', $id, 2);
                $pretty = count($parts) === 2
                    ? $parts[0].' - '.str_replace('_', ' ', $parts[1])
                    : str_replace('_', ' ', $id);
                $label = $pretty.' ('.$dt->format('H:i').')';
                $out[] = ['value' => $id, 'label' => $label];
            } catch (\Throwable) {
                continue;
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $out;
    }
}
