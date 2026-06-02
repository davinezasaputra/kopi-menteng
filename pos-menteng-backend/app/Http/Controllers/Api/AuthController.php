<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $request->email)->first();
        if (!$user|| !Hash::check($request->password, $user->password)){
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password salah',
            ], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil Halo',
            'data' => [
                'user' => $user,
                'nama' => $user->name,
                'token' => $token,
            ],
        ], 200);
    }
    public function loginPin(Request $request){
            $request->validate([
                'pin' => 'required',
            ]);
            $user = User::where('pin', $request->pin)->first();
            if (!$user){
                return response()->json([
                    'status' => 'error',
                    'message' => 'PIN salah',
                ], 401);
            }
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'status' => 'success',
                'message' => 'Login Berhasil Halo',
                'data' => [
                    'user' => $user,
                    'nama' => $user->name,
                    'token' => $token,
                ],
            ], 200);
    }
}
