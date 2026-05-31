<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditScoreController extends Controller
{
    /**
     * Credit score and payment-behaviour insights for a single client.
     */
    public function show(Request $request, Client $client): JsonResponse
    {
        abort_unless((int) $client->user_id === (int) $request->user()->id, 404);

        abort_unless($request->user()->tokenCan('clients:score'), 403, 'Token missing clients:score ability.');

        return response()->json([
            'data' => [
                'client_id' => $client->id,
                'name' => $client->name,
                'credit_score' => $client->credit_score,
                'risk_level' => $client->credit_risk_level,
                'flagged_for_review' => (bool) $client->flagged_for_review,
                'paid_invoice_count' => $client->paid_invoice_count,
                'avg_days_to_pay' => $client->avg_days_to_pay,
                'on_time_rate' => $client->on_time_rate,
                'late_count' => $client->late_count,
                'computed_at' => $client->behavior_recomputed_at?->toIso8601String(),
            ],
        ]);
    }
}
