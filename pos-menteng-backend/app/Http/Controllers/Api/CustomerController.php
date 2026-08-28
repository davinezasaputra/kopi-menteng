<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('points', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $customers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:customers,phone',
            'tier' => 'required|in:silver,gold,vip'
        ]);

        Customer::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Member baru berhasil didaftarkan.']);
    }

    // Fungsi khusus untuk dipanggil dari layar Kasir (POS)
    public function search(Request $request)
    {
        $request->validate(['phone' => 'required|string']);
        $customer = Customer::where('phone', $request->phone)->first();

        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Nomor HP tidak terdaftar.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $customer]);
    }
}