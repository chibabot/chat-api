<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class StatusController extends Controller
{
    public function __invoke()
    {
        try {
            // Проверка соединения с базой данных
            DB::connection()->getPdo();
            
            return response()->json([
                'status' => 'OK',
                'database' => 'Connected',
                'timestamp' => now()->toDateTimeString(),
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Service Unavailable',
                'database' => 'Not connected',
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}