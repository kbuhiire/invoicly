<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ReminderLog;
use App\Support\TwilioSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles Twilio delivery/read status callbacks for reminders sent over SMS and
 * WhatsApp (Phase 4). Twilio posts the MessageSid + MessageStatus here; we join
 * back to the reminder_logs row by provider_message_sid and update its status.
 */
class TwilioStatusController extends Controller
{
    /** Twilio statuses that mean the message did not reach the recipient. */
    private const FAILURE_STATUSES = ['failed', 'undelivered'];

    public function __invoke(Request $request): Response
    {
        $authToken = (string) config('services.twilio.token', '');

        abort_if($authToken === '', 403, 'Twilio is not configured.');
        abort_unless(
            TwilioSignature::isValid(
                $authToken,
                $request->fullUrl(),
                $request->post(),
                (string) $request->header('X-Twilio-Signature', '')
            ),
            403,
            'Invalid Twilio signature.'
        );

        $sid = (string) $request->input('MessageSid', '');
        $twilioStatus = (string) $request->input('MessageStatus', '');

        $log = $sid !== ''
            ? ReminderLog::where('provider_message_sid', $sid)->first()
            : null;

        if ($log !== null && $twilioStatus !== '') {
            $failed = in_array($twilioStatus, self::FAILURE_STATUSES, true);

            $log->forceFill([
                'status' => $failed ? ReminderLog::STATUS_FAILED : ReminderLog::STATUS_SENT,
                'error' => $failed ? "Twilio status: {$twilioStatus}" : null,
            ])->save();
        }

        // Twilio only needs a 2xx; body is ignored.
        return response()->noContent();
    }
}
