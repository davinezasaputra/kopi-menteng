<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $employees,
        ]);
    }

    public function show($id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Karyawan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $employee,
        ]);
    }

    public function search(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));

        $query = Employee::query();

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('WA', 'like', "%{$keyword}%")
                    ->orWhere('position', 'like', "%{$keyword}%");
            });
        }

        $employees = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $employees,
            'count' => $employees->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'WA' => 'required|string|max:20',
            'position' => 'required|string|max:50',
            'join_date' => 'nullable|date',
            'base_sallary' => 'required|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['id'] = (string) Str::uuid();

        $employee = Employee::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data karyawan berhasil ditambahkan.',
            'data' => $employee,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Karyawan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'WA' => 'sometimes|required|string|max:20',
            'position' => 'sometimes|required|string|max:50',
            'join_date' => 'nullable|date',
            'base_sallary' => 'sometimes|required|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $employee->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => $employee->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Karyawan tidak ditemukan.',
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data karyawan berhasil dihapus.',
        ]);
    }
}
