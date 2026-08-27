<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\RestockHistory;
use Illuminate\Http\Request;

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

            $material = RawMaterial::findOrFail($id);
            $imagePath = null;

            if ($request->hasFile('receipt_image')) {
                $imagePath = $request->file('receipt_image')->store('receipts', 'public');
            }

            // Hitung rata-rata harga satuan baru (Moving Average)
            $oldTotalValue = $material->stock * $material->price_per_unit;
            $newTotalValue = $oldTotalValue + $request->total_cost;
            $newStock = $material->stock + $request->quantity_added;
            
            $material->price_per_unit = $newTotalValue / $newStock;
            $material->stock = $newStock;
            $material->is_shopping_requested = false; // Otomatis hilang dari daftar belanja
            $material->save();

            RestockHistory::create([
                'raw_material_id' => $material->id,
                'quantity_added' => $request->quantity_added,
                'total_cost' => $request->total_cost,
                'receipt_image' => $imagePath,
                'restocked_by' => auth()->user()->name
            ]);

            return response()->json(['status' => 'success', 'message' => 'Stok diperbarui']);
    }
}

