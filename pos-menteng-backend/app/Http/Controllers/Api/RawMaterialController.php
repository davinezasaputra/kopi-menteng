<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
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
    }

