<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MenuImportController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly AuditService $audit) {}

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);
        $rows = Excel::toArray(null, $request->file('file'))[0] ?? [];
        if (count($rows) < 2) return response()->json(['status' => 'error', 'message' => 'File menu kosong atau hanya berisi header.'], 422);

        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($rows));
        foreach (['name', 'price'] as $required) {
            if (! in_array($required, $headers, true)) return response()->json(['status' => 'error', 'message' => "Header wajib '$required' tidak ditemukan."], 422);
        }

        $created = 0; $updated = 0; $failed = [];
        foreach ($rows as $index => $values) {
            $rowNumber = $index + 2; $row = [];
            foreach ($headers as $key => $header) $row[$header] = $values[$key] ?? null;
            $name = trim((string) ($row['name'] ?? ''));
            $price = is_numeric($row['price'] ?? null) ? (float) $row['price'] : -1;
            if ($name === '' || $price < 0) { $failed[] = ['row' => $rowNumber, 'errors' => ['Nama wajib diisi dan harga harus angka >= 0.']]; continue; }
            try {
                $product = Product::query()->where('tenant_id', $this->context->tenantId())->where('name', $name)->first();
                $exists = (bool) $product;
                $product ??= new Product();
                $product->tenant_id = $this->context->tenantId();
                $product->name = $name;
                $product->description = $row['description'] ?? null;
                $product->price = $price;
                $product->is_active = ! in_array(strtolower(trim((string) ($row['is_active'] ?? '1'))), ['0', 'false', 'no', 'nonaktif'], true);
                $categoryName = trim((string) ($row['category'] ?? ''));
                if ($categoryName !== '') $product->category_id = Category::firstOrCreate(['name' => $categoryName])->id;
                $product->save();
                $exists ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $failed[] = ['row' => $rowNumber, 'errors' => [$e->getMessage()]];
            }
        }

        $this->audit->record('menu_imported', 'inventory.products', null, null, [
            'created' => $created, 'updated' => $updated, 'failed' => count($failed),
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json([
            'status' => count($failed) ? 'warning' : 'success',
            'message' => count($failed) ? 'Import menu selesai dengan beberapa baris gagal.' : 'Import menu berhasil.',
            'created_count' => $created, 'updated_count' => $updated,
            'failed_count' => count($failed), 'failed_rows' => $failed,
        ], count($failed) ? 200 : 201);
    }

    public function template()
    {
        $filename = 'template_menu_'.now()->format('Y-m-d').'.csv';
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['name', 'category', 'price', 'description', 'is_active']);
            fputcsv($output, ['Kopi Susu', 'Minuman', '18000', 'Kopi susu gula aren', '1']);
            fputcsv($output, ['Croissant', 'Pastry', '22000', 'Croissant butter', '1']);
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
