<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReadinessController extends Controller
{
    public function __invoke()
    {
        try {
            DB::connection()->getPdo();
            return response()->json([
                'status'=>'ok',
                'checks'=>[
                    'database'=>['status'=>'ok'],
                ],
                'timestamp'=>now()->toISOString(),
            ],Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status'=>'error',
                'checks'=>[
                    'database'=>['status'=>'failed'],
                ],
                'timestamp'=>now()->toISOString(),
            ],Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
