<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
class ProductController extends Controller
{
    public function index(){
        $products = Product::with('category')->where('is_active', true)->get();

        return response()->json([
            'status'=>'success',
            'message'=>'Berhasil Menambahkan Produk',
            'data' => $products
        ], 200);
    }
    public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'price' => 'required|numeric',
        'stock' => 'required|numeric',
        'category_id' => 'required|string',
    ]);

    $product = Product::create([
        'name' => $request->name,
        'price' => $request->price,
        'stock' => $request->stock,
        'category_id' => $request->category_id,
    ]);

    return response()->json(['status' => 'success', 'data' => $product], 201);
}

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'stock' => 'required|numeric',
            'category_id' => 'required|string',
        ]);

        $product = Product::findOrFail($id);
        $product->update([
            'name' => $request->name,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
        ]);

        return response()->json(['status' => 'success', 'data' => $product], 200);
    }
}
