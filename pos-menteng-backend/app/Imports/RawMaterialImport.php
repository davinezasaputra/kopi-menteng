<?php

namespace App\Imports;

use App\Models\RawMaterial;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Database\Eloquent\Model;

class RawMaterialImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading, ShouldQueue
{
    private $failedRows = [];
    private $successCount = 0;

    public function model(array $row): Model|array|null
    {
        try {
            $name = $row['name'] ?? null;
            $category = $row['category'] ?? null;
            $unit = $row['unit'] ?? null;
            $stock = (float) ($row['stock'] ?? 0);
            
            if(!$name || !$category) return null;

            $existingMaterial = RawMaterial::where('name', $name)->where('category', $category)->first();
            if ($existingMaterial){
                $existingMaterial->increment('stock', $stock);
                $this->successCount++;
                return null;
            }
            $this->successCount++;
            return new RawMaterial([
                'name' => $name,
                'category' => $category,
                'unit' => $unit,
                'stock' => $stock,
                'is_requested' => false,
            ]);
        }catch (\Exception $e){
            $this->failedRows[] =[
                'row' => $row,
                'error'=> $e->getMessage(),
            ];
            return null;
        }
    }

    public function rules(): array
    {
        return [
            '*.name' => 'required|string|max:255',
            '*.category' => 'required|in:bar,dapur',
            '*.unit' => 'required|string|max:50',
            '*.stock' => 'required|numeric|min:0',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.name.required' => 'Kolom nama bahan tidak boleh kosong',
            '*.name.max' => 'Nama bahan maksimal 255 karakter',
            '*.category.required' => 'Kolom kategori (bar/dapur) harus diisi',
            '*.category.in' => 'Kategori hanya boleh "bar" atau "dapur"',
            '*.unit.required' => 'Kolom unit (gr, ml, pcs, dll) harus diisi',
            '*.unit.max' => 'Unit maksimal 50 karakter',
            '*.stock.required' => 'Kolom stok harus diisi',
            '*.stock.numeric' => 'Stok harus berupa angka',
            '*.stock.min' => 'Stok tidak boleh negatif',
        ];
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getFailedRows(): array
    {
        return $this->failedRows;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}
