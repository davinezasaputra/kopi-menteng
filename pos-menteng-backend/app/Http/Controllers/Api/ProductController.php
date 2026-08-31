<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

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
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'category_id' => 'required|string',
        ]);

        $product = Product::create([
            'tenant_id' => app(TenantContext::class)->tenantId(),
            'name' => $request->name,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
        ]);

        return response()->json(['status' => 'success', 'data' => $product], 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $product = Product::where('tenant_id', $tenantId)->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'category_id' => 'required|string',
        ]);

        $product->update([
            'name' => $request->name,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data produk berhasil diperbarui',
            'data' => $product->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $product = Product::where('tenant_id', $tenantId)->find($id);

        if (!$product) {
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

        if (!$product) {
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
