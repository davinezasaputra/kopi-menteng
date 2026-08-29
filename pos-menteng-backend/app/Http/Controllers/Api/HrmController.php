<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HrmController extends Controller
{
    // Mengambil Riwayat Absensi
    public function attendances()
    {
        $attendances = Attendance::with('user:id,name,role')->orderBy('clock_in', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $attendances]);
    }

    // Kasir Melakukan Absen Hadir
    public function clockIn(Request $request)
    {
        $user = $request->user();
        
        // Cek apakah sudah absen hari ini
        $alreadyClockedIn = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', Carbon::today())->first();

        if ($alreadyClockedIn) {
            return response()->json(['status' => 'error', 'message' => 'Anda sudah melakukan Clock-in hari ini.'], 400);
        }

        Attendance::create([
            'user_id' => $user->id,
            'clock_in' => now(),
            'status' => 'hadir' // Bisa dikembangkan jadi 'terlambat' jika > jam 8 pagi
        ]);

        return response()->json(['status' => 'success', 'message' => 'Berhasil Clock-in! Selamat bekerja.']);
    }

    // Mengambil Daftar Penggajian
    public function payrolls()
    {
        $payrolls = Payroll::with('user:id,name,role')->orderBy('period', 'desc')->orderBy('id', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $payrolls]);
    }

    // Membuat Slip Gaji
    public function generatePayroll(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period' => 'required|string', // Contoh: 2026-08
            'base_salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0'
        ]);

        $validated['bonus'] = $validated['bonus'] ?? 0;
        $validated['total_salary'] = $validated['base_salary'] + $validated['bonus'];

        Payroll::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Slip gaji berhasil diterbitkan.']);
    }

    // Tandai Gaji Sudah Ditransfer
    public function paySalary($id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->update(['is_paid' => true]);
        
        return response()->json(['status' => 'success', 'message' => 'Gaji ditandai telah dibayar!']);
    }
}