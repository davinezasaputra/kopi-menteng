<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly AuditService $audit, private readonly PinService $pinService) {}

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);
        $user = User::where('email', $request->email)->first();
        $key = 'login:'.strtolower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['status'=>'error','message'=>'Too many login attempts. Please try again later.'], 429);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);
            if ($user) {
                $this->audit->record('failed_login', 'auth.password', $user, null, ['reason'=>'invalid_credentials'], $request);
            }
            return response()->json(['status'=>'error','message'=>'Email atau password salah'], 401);
        }

        RateLimiter::clear($key);
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;
        $this->audit->record('login', 'auth', $user, null, ['method'=>'password'], $request);

        return response()->json([
            'status'=>'success',
            'message'=>'Login Berhasil Halo',
            'data'=>['user'=>$user,'nama'=>$user->name,'token'=>$token],
        ], 200);
    }

    public function loginPin(Request $request)
    {
        $request->validate(['pin' => ['required','digits:6']]);
        $userId = $request->input('user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json(['status'=>'error','message'=>'user_id is required for secure PIN authentication.'], 422);
        }

        $key = 'pin-login:'.$user->id.'|'.$request->ip();
        if (! $this->pinService->verify((string) $request->pin, $user, $key)) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['status'=>'error','message'=>'Too many PIN attempts. Please try again later.'], 429);
            }
            return response()->json(['status'=>'error','message'=>'PIN salah'], 401);
        }

        $token = $user->createToken('auth_token', ['pos'])->plainTextToken;
        $this->audit->record('login', 'auth.pin', $user, null, ['method'=>'pin'], $request);

        return response()->json([
            'status'=>'success',
            'message'=>'Login Berhasil Halo',
            'data'=>['user'=>$user,'nama'=>$user->name,'token'=>$token],
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $this->audit->record('logout', 'auth', $user, null, ['token_revoked'=>true], $request);
        $user?->currentAccessToken()?->delete();
        return response()->json(['status'=>'success','message'=>'Logout berhasil.']);
    }
}
