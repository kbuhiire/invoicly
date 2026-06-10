<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Issues sequential document numbers (invoices, quotes, credit notes) from
 * per-user, per-type counters with a configurable prefix / zero-padding /
 * year segment.
 *
 * Sequences are seeded lazily from the legacy number scan, so users who never
 * touch the settings keep getting EINV/DINV-YYYY-n exactly as before. A
 * collision-bump loop guards against a user lowering next_number below an
 * already-issued value.
 */
class DocumentNumberService
{
    public const TYPE_INVOICE_EXTERNAL = 'invoice_external';

    public const TYPE_INVOICE_INVOICLY = 'invoice_invoicly';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPES = [
        self::TYPE_INVOICE_EXTERNAL,
        self::TYPE_INVOICE_INVOICLY,
        self::TYPE_QUOTE,
        self::TYPE_CREDIT_NOTE,
    ];

    public const DEFAULT_PREFIXES = [
        self::TYPE_INVOICE_EXTERNAL => 'EINV',
        self::TYPE_INVOICE_INVOICLY => 'DINV',
        self::TYPE_QUOTE => 'QUO',
        self::TYPE_CREDIT_NOTE => 'CN',
    ];

    public function next(User $user, string $type, ?DateTimeInterface $date = null): string
    {
        $this->assertType($type);

        return DB::transaction(function () use ($user, $type, $date) {
            $sequence = $this->lockedSequence($user, $type, $date);

            $n = max(1, $sequence->next_number);
            do {
                $number = $this->format($sequence, $n, $date);
                $taken = $this->numberExists($user, $type, $number);
                if ($taken) {
                    $n++;
                }
            } while ($taken);

            $sequence->forceFill(['next_number' => $n + 1])->save();

            return $number;
        });
    }

    /**
     * Preview the next number without consuming it (settings live preview /
     * invoice form display).
     */
    public function preview(User $user, string $type, ?DateTimeInterface $date = null): string
    {
        $this->assertType($type);

        $sequence = DocumentSequence::query()
            ->where('user_id', $user->id)
            ->where('document_type', $type)
            ->first() ?? $this->defaultSequence($user, $type, $date);

        $n = max(1, $sequence->next_number);
        while ($this->numberExists($user, $type, $this->format($sequence, $n, $date))) {
            $n++;
        }

        return $this->format($sequence, $n, $date);
    }

    /**
     * The sequence as it would apply today, without persisting anything —
     * used to render settings for users who have never customized.
     */
    public function sequenceFor(User $user, string $type): DocumentSequence
    {
        $this->assertType($type);

        return DocumentSequence::query()
            ->where('user_id', $user->id)
            ->where('document_type', $type)
            ->first() ?? $this->defaultSequence($user, $type, null);
    }

    private function lockedSequence(User $user, string $type, ?DateTimeInterface $date): DocumentSequence
    {
        $existing = DocumentSequence::query()
            ->where('user_id', $user->id)
            ->where('document_type', $type)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $defaults = $this->defaultSequence($user, $type, $date);

        // firstOrCreate keeps a concurrent seeder from violating the unique
        // index; re-lock after creation.
        DocumentSequence::query()->firstOrCreate(
            ['user_id' => $user->id, 'document_type' => $type],
            $defaults->only(['prefix', 'next_number', 'padding', 'include_year'])
        );

        return DocumentSequence::query()
            ->where('user_id', $user->id)
            ->where('document_type', $type)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * In-memory sequence carrying the legacy defaults: default prefix, year
     * segment on, no padding, counter continuing from the legacy max scan.
     */
    private function defaultSequence(User $user, string $type, ?DateTimeInterface $date): DocumentSequence
    {
        $prefix = self::DEFAULT_PREFIXES[$type];

        return new DocumentSequence([
            'user_id' => $user->id,
            'document_type' => $type,
            'prefix' => $prefix,
            'next_number' => $this->legacyMax($user, $type, $prefix, $date) + 1,
            'padding' => 0,
            'include_year' => true,
        ]);
    }

    /**
     * Highest sequence number already issued under the default format for the
     * given year — the continuation point for un-customized users.
     */
    private function legacyMax(User $user, string $type, string $prefix, ?DateTimeInterface $date): int
    {
        $year = ($date ?? now())->format('Y');

        $numbers = $this->query($user, $type)
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->pluck('number');

        $max = 0;
        foreach ($numbers as $number) {
            if (preg_match('/-(\d+)$/', (string) $number, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    private function format(DocumentSequence $sequence, int $n, ?DateTimeInterface $date): string
    {
        $parts = [$sequence->prefix];

        if ($sequence->include_year) {
            $parts[] = ($date ?? now())->format('Y');
        }

        $parts[] = $sequence->padding > 0
            ? str_pad((string) $n, $sequence->padding, '0', STR_PAD_LEFT)
            : (string) $n;

        return implode('-', $parts);
    }

    private function numberExists(User $user, string $type, string $number): bool
    {
        return $this->query($user, $type)->where('number', $number)->exists();
    }

    private function query(User $user, string $type)
    {
        // Both invoice sequences draw from the invoices table: numbers are
        // unique per user across segments.
        $table = match ($type) {
            self::TYPE_QUOTE => 'quotes',
            self::TYPE_CREDIT_NOTE => 'credit_notes',
            default => 'invoices',
        };

        return DB::table($table)->where('user_id', $user->id);
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown document type [{$type}].");
        }
    }
}
