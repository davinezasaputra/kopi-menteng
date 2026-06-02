<?php

namespace App\Http\Controllers;

use App\Models\Category;
class CategoriesController extends Controller
{
    public function index(){
        $categories = Category::all();
        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil Mengambil Data Kategori Menu',
            'data' => $categories,
        ], 200);
    }
}
