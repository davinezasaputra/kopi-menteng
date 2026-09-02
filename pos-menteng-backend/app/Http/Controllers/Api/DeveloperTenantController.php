<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeveloperTenantController
{
    public function update(Request $request, int $tenant): JsonResponse
    {
        $record = Tenant::query()->findOrFail($tenant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('tenants', 'code')->ignore($record->id)],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
        $record->update($data);
        return response()->json(['status' => 'success', 'message' => 'Tenant berhasil diperbarui.', 'data' => $record->fresh()]);
    }
}
