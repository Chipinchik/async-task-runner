<?php

namespace App\Http\Controllers;

use App\Services\BrokerMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(BrokerMetrics $metrics): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $ready = $metrics->read()['available'];
        } catch (Throwable) {
            $ready = false;
        }

        return new JsonResponse(['status' => $ready ? 'ready' : 'unavailable'], $ready ? 200 : 503);
    }
}
