<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        $tenantId = app(TenantContext::class)->tenantId();

        $products = Product::with(['category', 'rawMaterials'])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diambil',
            'data' => $products,
        ], 200);
    }

    public function store(Request $request)
    {
        $context = app(TenantContext::class);

        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'category_id' => 'required|string',
        ]);

        $warehouse = Warehouse::query()
            ->where('branch_id', $context->branchId())
            ->where('is_default', true)
            ->first() ?? Warehouse::query()->where('branch_id', $context->branchId())->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse' => 'No warehouse is configured for the active branch.',
            ]);
        }

        $product = DB::transaction(function () use ($request, $context, $warehouse) {
            $openingQuantity = (float) $request->stock;

            $product = Product::create([
                'tenant_id' => $context->tenantId(),
                'name' => $request->name,
                'price' => $request->price,
                'stock' => $openingQuantity,
                'category_id' => $request->category_id,
            ]);

            InventoryBalance::create([
                'tenant_id' => $context->tenantId(),
                'company_id' => $context->companyId(),
                'branch_id' => $context->branchId(),
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => $openingQuantity,
                'reserved_quantity' => 0,
                'average_cost' => 0,
            ]);

            if ($openingQuantity > 0) {
                StockMovement::create([
                    'tenant_id' => $context->tenantId(),
                    'company_id' => $context->companyId(),
                    'branch_id' => $context->branchId(),
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'movement_type' => 'opening_balance',
                    'quantity' => $openingQuantity,
                    'unit_cost' => 0,
                    'balance_before' => 0,
                    'balance_after' => $openingQuantity,
                    'reference_type' => 'product_creation',
                    'reference_id' => (string) $product->id,
                    'created_by' => auth()->id(),
                    'request_id' => request()->attributes->get('request_id'),
                    'notes' => 'Opening inventory created with product.',
                    'created_at' => now(),
                ]);
            }

            return $product;
        });

        return response()->json(['status' => 'success', 'data' => $product], 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $product = Product::where('tenant_id', $tenantId)->find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|string',
        ]);

        $product->update([
            'name' => $request->name,
            'price' => $request->price,
            'category_id' => $request->category_id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data produk berhasil diperbarui. Gunakan Inventory API untuk perubahan stok.',
            'data' => $product->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $product = Product::where('tenant_id', $tenantId)->find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan di database',
            ], 404);
        }

        try {
            $product->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus! Produk ini tidak bisa dihapus karena sudah tercatat dalam riwayat transaksi kasir.',
            ], 400);
        }
    }

    public function syncRecipe(Request $request, $id)
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $product = Product::where('tenant_id', $tenantId)->find($id);

        if (! $product) {
            return response()->json(['status' => 'error', 'message' => 'Produk tidak ditemukan'], 404);
        }

        $request->validate([
            'recipe' => 'array',
            'recipe.*.raw_material_id' => 'required|exists:raw_materials,id',
            'recipe.*.quantity_needed' => 'required|numeric|min:0.01',
        ]);

        $syncData = [];
        if ($request->has('recipe')) {
            foreach ($request->recipe as $item) {
                $syncData[$item['raw_material_id']] = ['quantity_needed' => $item['quantity_needed']];
            }
        }

        $product->rawMaterials()->sync($syncData);

        return response()->json([
            'status' => 'success',
            'message' => 'Resep berhasil diperbarui',
        ]);
    }
}
