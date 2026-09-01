<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\RestockHistory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RawMaterialController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {}

    private function scopedQuery()
    {
        return RawMaterial::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());
    }

    private function findScoped(string $id): RawMaterial
    {
        return $this->scopedQuery()->findOrFail($id);
    }

    public function index(Request $request)
    {
        $materials = $this->scopedQuery()->orderBy('category')->orderBy('name')->paginate(min((int) $request->integer('per_page', 100), 200));
        return response()->json(['status' => 'success', 'data' => $materials]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'=>'required|string|max:255',
            'category'=>'required|in:bar,dapur',
            'unit'=>'required|string|max:30',
            'stock'=>'required|numeric|min:0',
            'min_stock_level'=>'nullable|numeric|min:0',
        ]);
        $validated += [
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
        ];
        $material = RawMaterial::create($validated);
        $this->audit->record('created', 'inventory.raw_material', $material, null, $material->toArray());
        return response()->json(['status'=>'success','data'=>$material],201);
    }

    public function update(Request $request, string $id)
    {
        $material = $this->findScoped($id);
        $validated = $request->validate([
            'name'=>'required|string|max:255',
            'category'=>'required|in:bar,dapur',
            'unit'=>'required|string|max:30',
            'min_stock_level'=>'nullable|numeric|min:0',
        ]);
        $old = $material->toArray();
        $material->update($validated);
        $this->audit->record('updated', 'inventory.raw_material', $material, $old, $material->fresh()->toArray());
        return response()->json(['status'=>'success','data'=>$material->fresh()]);
    }

    public function destroy(string $id)
    {
        $material = $this->findScoped($id);
        $old = $material->toArray();
        $material->delete();
        $this->audit->record('deleted', 'inventory.raw_material', $material, $old, null);
        return response()->json(['status'=>'success','message'=>'Bahan Baku berhasil dihapus']);
    }

    public function toggleShoppingRequest(string $id)
    {
        $material = $this->findScoped($id);
        $old = $material->toArray();
        $material->is_requested = ! $material->is_requested;
        $material->save();
        $this->audit->record('updated', 'inventory.raw_material', $material, $old, $material->fresh()->toArray());
        return response()->json(['status'=>'success','message'=>'Status belanja diperbarui','data'=>$material->fresh()]);
    }

    public function restock(Request $request, string $id)
    {
        $validated = $request->validate([
            'quantity_added'=>'required|numeric|min:1',
            'total_cost'=>'required|numeric|min:0',
            'receipt_image'=>'nullable|image|max:2048'
        ]);

        $imagePath = $request->hasFile('receipt_image') ? $request->file('receipt_image')->store('receipts','public') : null;
        $restock = DB::transaction(function () use ($validated, $imagePath, $id) {
            $material = $this->findScoped($id);
            $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
            $old = $material->toArray();
            $oldTotalValue = (float) $material->stock * (float) $material->price_per_unit;
            $newStock = (float) $material->stock + (float) $validated['quantity_added'];
            $material->price_per_unit = $newStock > 0 ? ($oldTotalValue + (float) $validated['total_cost']) / $newStock : 0;
            $material->stock = $newStock;
            $material->is_requested = false;
            $material->save();
            $restock = RestockHistory::create([
                'tenant_id' => $this->context->tenantId(),
                'company_id' => $this->context->companyId(),
                'branch_id' => $this->context->branchId(),
                'raw_material_id' => $material->id,
                'quantity_added' => $validated['quantity_added'],
                'total_cost' => $validated['total_cost'],
                'receipt_image' => $imagePath,
                'restocked_by' => auth()->user()?->name ?? 'System',
            ]);
            $this->audit->record('restocked', 'inventory.raw_material', $material, $old, $material->fresh()->toArray());
            return $restock;
        });

        return response()->json(['status'=>'success','message'=>'Stok dan biaya pembelian berhasil dicatat','data'=>$restock]);
    }
}
