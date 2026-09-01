<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PinService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly AuditService $audit, private readonly PinService $pinService, private readonly TenantContext $context) {}

    private function hydrateContext(User $user): bool
    {
        try { $this->context->resolveFor($user); return true; } catch (\Throwable) { return false; }
    }

    private function auditIfScoped(string $event, string $module, User $user, Request $request, array $data): void
    {
        if ($this->context->membership()) $this->audit->record($event,$module,$user,null,$data,$request);
    }

    public function login(Request $request)
    {
        $request->validate(['email'=>'required|email','password'=>'required']);
        $user=User::where('email',$request->email)->first();
        $key='login:'.strtolower($request->email).'|'.$request->ip();
        if(RateLimiter::tooManyAttempts($key,10)) return response()->json(['status'=>'error','message'=>'Too many login attempts. Please try again later.'],429);
        if(!$user || !Hash::check($request->password,$user->password)){
            RateLimiter::hit($key,60);
            if($user && $this->hydrateContext($user)) $this->auditIfScoped('failed_login','auth.password',$user,$request,['reason'=>'invalid_credentials']);
            return response()->json(['status'=>'error','message'=>'Email atau password salah'],401);
        }
        RateLimiter::clear($key); $this->hydrateContext($user);
        $token=$user->createToken('auth_token',['*'])->plainTextToken;
        $this->auditIfScoped('login','auth',$user,$request,['method'=>'password']);
        return response()->json(['status'=>'success','message'=>'Login Berhasil Halo','data'=>['user'=>$user,'nama'=>$user->name,'token'=>$token]],200);
    }

    public function loginPin(Request $request)
    {
        $request->validate(['pin'=>['required','digits:6'],'user_id'=>['nullable','integer','exists:users,id']]);
        $pin=(string)$request->pin;
        $clientKey='pin-login:'.($request->user_id ? $request->user_id : hash('sha256',$request->ip())).'|'.$request->ip();
        if(RateLimiter::tooManyAttempts($clientKey,5)) return response()->json(['status'=>'error','message'=>'Too many PIN attempts. Please try again later.'],429);

        $user=$request->filled('user_id') ? User::findOrFail($request->integer('user_id')) : $this->pinService->findByPin($pin);
        if(!$user || !$this->pinService->verify($pin,$user,$clientKey)){
            RateLimiter::hit($clientKey,60);
            if($user && $this->hydrateContext($user)) $this->auditIfScoped('failed_login','auth.pin',$user,$request,['reason'=>'invalid_pin']);
            return response()->json(['status'=>'error','message'=>'PIN salah'],401);
        }
        $this->hydrateContext($user);
        $token=$user->createToken('auth_token',['pos'])->plainTextToken;
        $this->auditIfScoped('login','auth.pin',$user,$request,['method'=>'pin']);
        return response()->json(['status'=>'success','message'=>'Login Berhasil Halo','data'=>['user'=>$user,'nama'=>$user->name,'token'=>$token]],200);
    }

    public function logout(Request $request)
    {
        $user=$request->user(); $this->auditIfScoped('logout','auth',$user,$request,['token_revoked'=>true]); $user?->currentAccessToken()?->delete();
        return response()->json(['status'=>'success','message'=>'Logout berhasil.']);
    }
}
