<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    private function scopedQuery(Request $request)
    {
        return Shift::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->where('user_id', $request->user()->id);
    }

    public function status(Request $request)
    {
        $activeShift = $this->scopedQuery($request)->where('status', 'open')->first();

        return response()->json([
            'status' => 'success',
            'is_open' => (bool) $activeShift,
            'data' => $activeShift,
        ]);
    }

    public function open(Request $request)
    {
        $activeShift = $this->scopedQuery($request)->where('status', 'open')->first();

        if ($activeShift) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda masih memiliki shift yang belum ditutup',
                'data' => $activeShift,
            ], 400);
        }

        $data = $request->validate([
            'starting_cash' => ['required','numeric','min:0'],
            'warehouse_id' => ['required','integer','exists:warehouses,id'],
        ]);

        $warehouse = Warehouse::query()
            ->whereKey($data['warehouse_id'])
            ->where('branch_id', $this->context->branchId())
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Warehouse is outside the active branch.',
            ]);
        }

        $shift = Shift::create([
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
            'warehouse_id' => $warehouse->id,
            'user_id' => $request->user()->id,
            'start_time' => now(),
            'starting_cash' => $data['starting_cash'],
            'expected_ending_cash' => $data['starting_cash'],
            'status' => 'open',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Shift Dibuka',
            'data' => $shift,
        ], 201);
    }

    public function close(Request $request)
    {
        $activeShift = $this->scopedQuery($request)->where('status', 'open')->first();

        if (! $activeShift) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki shift yang sedang aktif',
            ], 404);
        }

        $data = $request->validate([
            'actual_ending_cash' => ['required','numeric','min:0'],
        ]);

        $activeShift->update([
            'end_time' => Carbon::now(),
            'actual_ending_cash' => $data['actual_ending_cash'],
            'status' => 'closed',
        ]);

        $activeShift->refresh();

        $selisih = $data['actual_ending_cash'] - $activeShift->expected_ending_cash;
        $pesanSelisih = $selisih == 0
            ? 'Saldo Balance.'
            : 'Terdapat Selisih Saldo Sebesar '.number_format($selisih, 0, ',', '.');

        return response()->json([
            'status' => 'success',
            'message' => 'Shift Berhasil di Tutup. '.$pesanSelisih,
            'data' => $activeShift,
        ]);
    }
}
