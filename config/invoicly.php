<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credit scoring
    |--------------------------------------------------------------------------
    |
    | A client's credit_score (0-100, higher = safer) is a weighted blend of
    | four heuristic components. Weights should sum to 1.0. risk_level is then
    | derived from the score using the thresholds below.
    |
    */
    'credit' => [
        // Minimum number of paid invoices before a score is produced. Below
        // this the client is reported as "insufficient history" (null score).
        'min_history' => (int) env('INVOICLY_CREDIT_MIN_HISTORY', 2),

        'weights' => [
            'on_time' => 0.50, // share of invoices paid on or before due date
            'speed' => 0.20, // how quickly they pay (avg days to pay)
            'overdue' => 0.20, // current overdue exposure vs typical invoice
            'history' => 0.10, // depth of payment history (sample size)
        ],

        // score >= low  => low risk; >= medium => medium risk; else high risk.
        'thresholds' => [
            'low' => 70,
            'medium' => 40,
        ],

        // "speed" component reaches 0 at this many average days-to-pay.
        'slow_days_floor' => 50,

        // "history" component reaches 100 at this many paid invoices.
        'history_full_at' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash-flow forecasting
    |--------------------------------------------------------------------------
    */
    'forecast' => [
        // Weeks projected ahead.
        'horizon_weeks' => (int) env('INVOICLY_FORECAST_HORIZON_WEEKS', 12),

        // Fallback days-to-pay when a client has too little history to predict.
        'default_days_to_pay' => (int) env('INVOICLY_FORECAST_DEFAULT_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart reminders
    |--------------------------------------------------------------------------
    |
    | Reminders are scheduled (reminders:send) and timed off each client's
    | due date, nudged earlier for clients who historically pay late (their
    | avg_days_to_pay exceeds the invoice's net terms). Delivery is attempted
    | over the channels below in order; a client's own preferred_contact_method
    | is tried ahead of this list. Each reminder stage is sent once — dedupe is
    | enforced via the reminder_logs table.
    |
    */
    'reminders' => [
        'enabled' => (bool) env('INVOICLY_REMINDERS_ENABLED', true),

        // Channel preference order. First configured + reachable channel wins.
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INVOICLY_REMINDER_CHANNELS', 'email,whatsapp,sms,slack'))
        ))),

        // Days before the due date to send the "upcoming" heads-up.
        'lead_days' => (int) env('INVOICLY_REMINDER_LEAD_DAYS', 3),

        // Chronic-late clients (avg_days_to_pay beyond their net terms, or
        // flagged high risk) get the heads-up this many extra days earlier.
        'late_payer_extra_lead_days' => (int) env('INVOICLY_REMINDER_LATE_LEAD_DAYS', 4),

        // Grace period after the due date before the first overdue reminder.
        'overdue_grace_days' => (int) env('INVOICLY_REMINDER_OVERDUE_GRACE_DAYS', 1),

        // Minimum days between successive overdue reminders for one invoice.
        'overdue_interval_days' => (int) env('INVOICLY_REMINDER_OVERDUE_INTERVAL_DAYS', 7),

        // Stop nagging after this many overdue reminders on a single invoice.
        'max_overdue_reminders' => (int) env('INVOICLY_REMINDER_MAX_OVERDUE', 4),

        // Don't remind on invoices with an outstanding balance below this
        // (noise control). Compared in the invoice's own currency.
        'min_outstanding' => (string) env('INVOICLY_REMINDER_MIN_OUTSTANDING', '0'),
    ],

];
