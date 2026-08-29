<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OperationalExpense;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
   // Tandai Gaji Sudah Ditransfer & Catat Pengeluaran Otomatis
    public function paySalary($id)
    {
        $payroll = Payroll::with('user')->findOrFail($id);
        
        // Proteksi agar gaji tidak dibayar dua kali
        if ($payroll->is_paid) {
            return response()->json(['status' => 'error', 'message' => 'Gaji ini sudah ditransfer sebelumnya.'], 400);
        }

            DB::beginTransaction();
        try {
            // 1. Ubah status Slip Gaji menjadi Lunas
            $payroll->update(['is_paid' => true]);
            
            // 2. Tembakkan datanya otomatis ke Kotak Pengeluaran Dasbor (OpEx)
            OperationalExpense::create([
                'name' => 'Pembayaran Gaji: ' . $payroll->user->name . ' (Periode ' . $payroll->period . ')',
                'amount' => $payroll->total_salary,
                'expense_date' => Carbon::today(),
                'recorded_by' => 'Sistem HRIS (Otomatis)'
            ]);

            DB::commit();
            
            return response()->json([
                'status' => 'success', 
                'message' => 'Gaji berhasil dibayar dan Pengeluaran otomatis dicatat di Dasbor!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error', 
                'message' => 'Terjadi kesalahan sinkronisasi ERP: ' . $e->getMessage()
            ], 500);
        }
    }
}