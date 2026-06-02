<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function open(Request $request){
        $user = $request->user();
        $activeShift = Shift::where('user_id', $user->id)->where('status', 'open')->first();

        if ($activeShift){
            return response()->json([
                'status' => 'error',
                'message' => 'Anda masih memiliki shift yang belum ditutup', 
                'data' => $activeShift
            ], 400);
        }

        $request->validate([
            'starting_cash' => 'required|numeric|min:0',
        ]);

        $shift = Shift::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'starting_cash' => $request->starting_cash,
            'expected_ending_cash' => $request->starting_cash,
            'status' => 'open',
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Shift Dibuka',
            'data' => $shift
        ], 201);
    }

    public function close(Request $request){
        $user = $request->user();
        $activeShift = Shift::where('user_id', $user->id)->where('status', 'open')->first();

        if (!$activeShift){
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki shift yang sedang aktif'
            ], 404);
        }
        $request->validate([
            'actual_ending_cash' => 'required|numeric|min:0',
        ]);

        $activeShift->update([
            'end_time' => Carbon::now(),
            'actual_ending_cash' => $request->actual_ending_cash,
            'status' => 'closed',
        ]);

        $activeShift->refresh();

        $selisih = $request->actual_ending_cash - $activeShift->expected_ending_cash;
        $pesanSelisih = $selisih == 0 ? 'Saldo Balance.' : 'Terdapat Selisih Saldo Sebesar '.number_format($selisih,0,',','.');
        return response()->json([
            'status' => 'success',
            'message'=> 'Shift Berhasil di Tutup. ' . $pesanSelisih,
            'data' => $activeShift
        ],200);
        
    }
}
