<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\ReceiptTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ReceiptTemplateController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {}

    private function scope(): array
    {
        return [
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
        ];
    }

    private function defaults(): array
    {
        return [
            'business_name' => 'KOPI MENTENG',
            'address' => 'Jl. Jenderal Sudirman',
            'phone' => null,
            'logo_url' => null,
            'paper_width' => '80mm',
            'show_cashier' => true,
            'show_customer' => true,
            'show_order_type' => true,
            'show_tax' => true,
            'show_discount' => true,
            'show_sku' => false,
            'show_change' => true,
            'footer_text' => 'Terima kasih atas kunjungan Anda!',
            'wifi_text' => 'WiFi: kopimenteng_guest',
            'is_active' => true,
        ];
    }

    public function show()
    {
        $template = ReceiptTemplate::query()->where($this->scope())->first();

        return response()->json([
            'status' => 'success',
            'data' => array_merge($this->defaults(), $template?->toArray() ?? []),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'paper_width' => ['required', 'in:58mm,80mm'],
            'show_cashier' => ['required', 'boolean'],
            'show_customer' => ['required', 'boolean'],
            'show_order_type' => ['required', 'boolean'],
            'show_tax' => ['required', 'boolean'],
            'show_discount' => ['required', 'boolean'],
            'show_sku' => ['required', 'boolean'],
            'show_change' => ['required', 'boolean'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'wifi_text' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $scope = $this->scope();
        $template = ReceiptTemplate::query()->firstOrNew($scope);
        $before = $template->exists ? $template->toArray() : null;
        $template->fill($validated);
        $template->save();

        $this->audit->record(
            $template->wasRecentlyCreated ? 'created' : 'updated',
            'pos.receipt_template',
            $template,
            $before,
            $template->fresh()->toArray(),
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Template nota berhasil disimpan.',
            'data' => array_merge($this->defaults(), $template->fresh()->toArray()),
        ]);
    }
}
