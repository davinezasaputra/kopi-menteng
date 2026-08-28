<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\RestockHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RawMaterialController extends Controller
{
    public function index()
    {
        $materials = RawMaterial::orderBy('category')->orderBy('name')->get();
        return response()->json([
            'status' => 'success',
            'data' => $materials
        ]);
    }
    public function store(Request $request){
        $request->validate([
            'name'=>'required|string',
            'category'=>'required|in:bar,dapur',
            'unit'=>'required|string',
            'stock'=>'required|numeric',
            'min_stock_level'=>'nullable|numeric|min:0',
        ]);

        $materials = RawMaterial::create($request->all());
        return response()->json([
            'status'=>'success',
            'data'=>$materials
        ],201);
    }
    public function update(Request $request, $id){
        $materials = RawMaterial::findOrFail($id);
        try {
            $request->validate([
                'name'=>'required|string',
                'category'=>'required|in:bar,dapur',
                'unit'=>'required|string',
                'stock'=>'required|numeric',
                'min_stock_level'=>'nullable|numeric|min:0',
            ]);
            $data = $request->all();
            $data['is_requested'] = false;
            $materials->update($data);

            return response()->json([
                'status'=>'success',
                'data'=>$materials
            ],200);

        } catch (\Exception $e) {
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],400);
        }
    }
    public function destroy($id){
        $materials = RawMaterial::findOrFail($id);
        try { 
            $materials->delete();
            return response()->json([
                'status'=>'success',
                'message'=>'Bahan Baku berhasil dihapus'
            ],200);
        } catch (\Exception $e) {
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],400);
        }
    }
    public function toggleShoppingRequest($id){
        $material = RawMaterial::findOrFail($id);
        
        // Membalikkan nilai boolean secara manual
        $material->is_requested = !$material->is_requested;
        $material->save();

        return response()->json([
            'status' => 'success', 
            'message' => 'Status belanja diperbarui',
            'data' => $material
        ]);
        }
    public function restock(Request $request, $id)
    {
            $request->validate([
                'quantity_added' => 'required|numeric|min:1',
                'total_cost' => 'required|numeric|min:0',
                'receipt_image' => 'nullable|image|max:2048' // Maks 2MB
            ]);

            $imagePath = null;

            if ($request->hasFile('receipt_image')) {
                $imagePath = $request->file('receipt_image')->store('receipts', 'public');
            }

            $restock = DB::transaction(function () use ($request, $id, $imagePath) {
                $material = RawMaterial::lockForUpdate()->findOrFail($id);

                // Hitung rata-rata harga satuan baru (Moving Average).
                $oldTotalValue = $material->stock * $material->price_per_unit;
                $newStock = $material->stock + $request->quantity_added;
                $material->price_per_unit = ($oldTotalValue + $request->total_cost) / $newStock;
                $material->stock = $newStock;
                $material->is_requested = false;
                $material->save();

                return RestockHistory::create([
                    'raw_material_id' => $material->id,
                    'quantity_added' => $request->quantity_added,
                    'total_cost' => $request->total_cost,
                    'receipt_image' => $imagePath,
                    'restocked_by' => $request->user()?->name ?? 'System'
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Stok dan biaya pembelian berhasil dicatat',
                'data' => $restock
            ]);
    }
}

