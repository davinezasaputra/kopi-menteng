<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\RawMaterialImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function importRawMaterials(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // Max 5MB
        ]);

        try {
            $import = new RawMaterialImport();
            Excel::import($import, $request->file('file'));

            $failedRows = $import->getFailedRows();

            if (count($failedRows) > 0) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Import sebagian berhasil dengan beberapa kesalahan',
                    'imported_count' => $import->getSuccessCount(),
                    'failed_rows' => $failedRows,
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Import data bahan baku berhasil',
                'imported_count' => $import->getSuccessCount(),
            ], 201);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validasi import gagal',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengimport file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        $filename = 'template_bahan_baku_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');

            // Header row
            fputcsv($output, ['name', 'category', 'unit', 'stock'], ',');

            // Example rows
            fputcsv($output, ['Kopi Arabika', 'dapur', 'kg', '50'], ',');
            fputcsv($output, ['Susu Cair', 'bar', 'liter', '30'], ',');
            fputcsv($output, ['Gula Pasir', 'dapur', 'kg', '25'], ',');

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
