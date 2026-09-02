<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Imports\MenuProductImport;
use App\Imports\RawMaterialImport;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ImportController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function importRawMaterials(Request $request)
    {
        $request->validate(['file'=>'required|file|mimes:xlsx,xls,csv|max:5120']);
        try {
            $reader = new MenuProductImport();
            $sheets = Excel::toCollection($reader, $request->file('file'));
            $rows = $sheets->first() ?? collect();
            $hasMenuHeader = $rows->first() && collect($rows->first())->keys()->intersect(['price','description','is_active'])->isNotEmpty();

            if ($hasMenuHeader) {
                return $this->processMenuImport($rows);
            }

            $import = new RawMaterialImport();
            Excel::import($import, $request->file('file'));
            $failedRows = $import->getFailedRows();
            return response()->json([
                'status' => count($failedRows) > 0 ? 'warning' : 'success',
                'message' => count($failedRows) > 0 ? 'Import bahan baku sebagian berhasil.' : 'Import data bahan baku berhasil.',
                'imported_count' => $import->getSuccessCount(),
                'failed_rows' => $failedRows,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['status'=>'error','message'=>'Validasi import gagal','errors'=>collect($e->failures())->map(fn($failure)=>['row'=>$failure->row(),'attribute'=>$failure->attribute(),'errors'=>$failure->errors(),'values'=>$failure->values()])->values()],422);
        } catch (\Throwable $e) {
            return response()->json(['status'=>'error','message'=>'Terjadi kesalahan saat mengimport file: '.$e->getMessage()],500);
        }
    }

    private function processMenuImport($rows)
    {
        $warehouse=Warehouse::query()->where('branch_id',$this->context->branchId())->where('is_default',true)->first()
            ?? Warehouse::query()->where('branch_id',$this->context->branchId())->where('status','active')->first();
        if(!$warehouse&&$this->context->branchId()!==null){
            $warehouse=Warehouse::create(['branch_id'=>$this->context->branchId(),'code'=>'MAIN','name'=>'Main Warehouse','type'=>'store','is_default'=>true,'status'=>'active']);
        }
        if(!$warehouse)return response()->json(['status'=>'error','message'=>'Branch aktif belum memiliki warehouse dan warehouse tidak dapat dibuat otomatis.'],422);

        $created=0;$updated=0;$failed=0;$failedRows=[];
        foreach($rows as $index=>$raw){
            $row=$raw instanceof \Illuminate\Support\Collection?$raw:$this->toCollection($raw);
            $line=$index+2;
            try {
                $name=trim((string)($row['name']??''));
                $price=$row['price']??null;
                if($name==='')throw new \InvalidArgumentException('name wajib diisi.');
                if($price===null||$price===''||!is_numeric($price)||((float)$price)<0)throw new \InvalidArgumentException('price wajib berupa angka >= 0.');
                $categoryName=trim((string)($row['category']??''));
                if($categoryName==='')throw new \InvalidArgumentException('category wajib diisi.');
                $active=$this->booleanValue($row['is_active']??1);
                $description=isset($row['description'])?trim((string)$row['description']):null;
                DB::transaction(function()use($name,$price,$categoryName,$active,$description,$warehouse,&$created,&$updated):void{
                    $category=Category::firstOrCreate(['name'=>$categoryName]);
                    $query=Product::query()->where('tenant_id',$this->context->tenantId())->where('name',$name);
                    $product=$query->first();
                    if($product){$product->update(['category_id'=>$category->id,'name'=>$name,'description'=>$description,'price'=>(float)$price,'is_active'=>$active]);$updated++;}
                    else{$product=Product::create(['tenant_id'=>$this->context->tenantId(),'category_id'=>$category->id,'name'=>$name,'description'=>$description,'price'=>(float)$price,'stock'=>0,'is_active'=>$active]);$created++;}
                    InventoryBalance::updateOrCreate(['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'warehouse_id'=>$warehouse->id,'product_id'=>$product->id],['quantity'=>0,'reserved_quantity'=>0,'average_cost'=>0]);
                });
            } catch(\Throwable $e) {$failed++;$failedRows[]=['row'=>$line,'name'=>(string)($row['name']??''),'errors'=>[$e->getMessage()],'values'=>$row->toArray()];}
        }
        return response()->json(['status'=>$failed>0?'warning':'success','message'=>$failed>0?'Import menu selesai dengan beberapa baris gagal.':'Import menu berhasil.','created_count'=>$created,'updated_count'=>$updated,'failed_count'=>$failed,'failed_rows'=>$failedRows],200);
    }

    private function toCollection($row)
    {
        return collect((array)$row);
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1','true','yes','ya','aktif','active'], true);
    }

    public function downloadTemplate()
    {
        $filename='template_bahan_baku_'.now()->format('Y-m-d').'.csv';
        return response()->streamDownload(function(){ $output=fopen('php://output','w'); fputcsv($output,['name','category','unit','stock'],','); fputcsv($output,['Kopi Arabika','dapur','kg','50'],','); fputcsv($output,['Susu Cair','bar','liter','30'],','); fputcsv($output,['Gula Pasir','dapur','kg','25'],','); fclose($output); },$filename,['Content-Type'=>'text/csv','Content-Disposition'=>'attachment; filename="'.$filename.'"']);
    }
}
