<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientType;
use App\Http\Controllers\Controller;
use App\Services\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function __construct(private readonly ForecastService $forecast) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('forecasts:read'), 403, 'Token missing forecasts:read ability.');

        $segment = match ($request->string('segment')->toString()) {
            'invoicly' => ClientType::Invoicly,
            'external' => ClientType::External,
            default => null,
        };

        return response()->json([
            'data' => $this->forecast->forUser($request->user(), $segment),
        ]);
    }
}
