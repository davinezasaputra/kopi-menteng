<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class Idempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(strtoupper($request->method()), ['GET','HEAD','OPTIONS'], true)) {
            return $next($request);
        }

        $key=$request->header('X-Idempotency-Key');
        if (!$key) {
            return $next($request);
        }

        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/',$key)) {
            return response()->json([
                'message'=>'Invalid X-Idempotency-Key format.'
            ],422);
        }

        $userId=$request->user()?->id;
        $tenantId=null;

        try {
            $tenantId=app(TenantContext::class)->tenantId();
        } catch (\Throwable) {
            // Authentication/tenant middleware may run after this middleware in another route.
        }

        $hash=hash('sha256',json_encode([
            'query'=>$request->query(),
            'body'=>$request->all(),
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

        $existing=DB::table('api_idempotency_keys')
            ->where('key',$key)
            ->where('method',strtoupper($request->method()))
            ->where('route',$request->path())
            ->when($userId,fn($q)=>$q->where('user_id',$userId))
            ->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $hash) {
                return response()->json([
                    'message'=>'Idempotency key was already used with a different request payload.'
                ],409);
            }

            if ($existing->completed_at !== null) {
                return response($existing->response_body ?? '',$existing->response_status ?? 200,[
                    'Content-Type'=>'application/json',
                    'X-Idempotent-Replay'=>'true',
                ]);
            }

            return response()->json([
                'message'=>'An identical request is already being processed.'
            ],409);
        }

        $id=DB::table('api_idempotency_keys')->insertGetId([
            'key'=>$key,
            'method'=>strtoupper($request->method()),
            'route'=>$request->path(),
            'user_id'=>$userId,
            'tenant_id'=>$tenantId,
            'request_hash'=>$hash,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        try {
            $response=$next($request);

            if ($response->isSuccessful() || $response->isRedirection()) {
                DB::table('api_idempotency_keys')->where('id',$id)->update([
                    'response_status'=>$response->getStatusCode(),
                    'response_body'=>$response->getContent(),
                    'completed_at'=>now(),
                    'updated_at'=>now(),
                ]);
            } else {
                DB::table('api_idempotency_keys')->where('id',$id)->delete();
            }

            $response->headers->set('X-Idempotency-Key',$key);
            return $response;
        } catch (\Throwable $e) {
            DB::table('api_idempotency_keys')->where('id',$id)->delete();
            throw $e;
        }
    }
}
