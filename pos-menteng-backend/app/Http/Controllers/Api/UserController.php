<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        // Tampilkan semua karyawan
        $users = User::orderBy('role', 'asc')->get();
        return response()->json(['status' => 'success', 'data' => $users]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,manager,kasir'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        User::create($validated);

        return response()->json(['status' => 'success', 'message' => 'Akun karyawan berhasil dibuat.']);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Proteksi: Admin utama (ID 1) tidak boleh dihapus agar sistem tidak terkunci
        if ($user->id === 1) {
            return response()->json(['status' => 'error', 'message' => 'Akun Admin Utama tidak bisa dihapus!'], 403);
        }

        $user->delete();
        return response()->json(['status' => 'success', 'message' => 'Akun karyawan diberhentikan.']);
    }
}