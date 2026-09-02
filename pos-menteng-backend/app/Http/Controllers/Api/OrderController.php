<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Services\PosOrderAccountingService;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Inventory\Services\InventoryService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReceiptTemplate;
use App\Models\Shift;
use App\Support\Tenancy\OrganizationScope;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly OrganizationScope $scope,
        private readonly InventoryService $inventory,
        private readonly AuditService $audit,
        private readonly PosOrderAccountingService $accounting,
    ) {}

    public function index(Request $request)
    {
        $orders = Order::with('items.product')
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->when($this->context->locationId() !== null, fn ($query) => $query->where('location_id', $this->context->locationId()))
            ->whereDate('created_at', $request->date('date', Carbon::today()))
            ->orderByDesc('created_at')->paginate(min((int) $request->integer('per_page', 50), 100));
        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user(); $tenantId = $this->context->tenantId(); $companyId = $this->context->companyId(); $branchId = $this->context->branchId();
        $locationId = $this->scope->requireOperationalLocation()->id;
        $activeShift = Shift::query()->where('tenant_id',$tenantId)->where('company_id',$companyId)->where('branch_id',$branchId)->where('location_id',$locationId)->where('user_id',$user->id)->where('status','open')->first();
        if (! $activeShift) return response()->json(['status'=>'error','message'=>'Anda harus membuka shift terlebih dahulu.'],403);
        if (! $activeShift->warehouse_id) return response()->json(['status'=>'error','message'=>'Shift belum terhubung ke warehouse.'],422);
        $warehouse = $this->scope->warehouse((int)$activeShift->warehouse_id);
        if (! $warehouse) return response()->json(['status'=>'error','message'=>'Warehouse shift berada di luar location scope aktif.'],403);

        $validated = $request->validate([
            'payment_method'=>'required|in:cash,qris,bank_transfer,unpaid','order_type'=>'required|in:dine_in,takeaway',
            'customer_id'=>'nullable|exists:customers,id','customer_name'=>'nullable|string|max:100','items'=>'required|array|min:1',
            'items.*.product_id'=>'required|exists:products,id','items.*.quantity'=>'required|integer|min:1|max:1000',
        ]);

        try {
            DB::beginTransaction(); $subtotal=0; $totalCogs=0; $processedItems=[];
            foreach ($validated['items'] as $item) {
                $product = Product::query()->where('tenant_id',$tenantId)->where('is_active',true)->lockForUpdate()->with(['rawMaterials'=>function($query)use($tenantId,$companyId,$branchId){$query->where('raw_materials.tenant_id',$tenantId)->where('raw_materials.company_id',$companyId)->where('raw_materials.branch_id',$branchId);}])->findOrFail($item['product_id']);
                $qty=(int)$item['quantity']; $itemSubtotal=(float)$product->price*$qty; $subtotal+=$itemSubtotal; $itemCogs=0;
                foreach($product->rawMaterials as $material){$needed=(float)$material->pivot->quantity_needed*$qty;if((float)$material->stock<$needed)throw new \RuntimeException("Bahan baku '{$material->name}' tidak mencukupi. Available: {$material->stock}.");$material->decrement('stock',$needed);$materialAfter=$material->fresh();if((float)$materialAfter->stock<=(float)$materialAfter->min_stock_level)$materialAfter->update(['is_requested'=>true]);$itemCogs+=$needed*(float)$material->price_per_unit;}
                $totalCogs+=$itemCogs; $processedItems[]=['product_id'=>$product->id,'quantity'=>$qty,'unit_price'=>$product->price,'subtotal'=>$itemSubtotal];
            }
            $customer=null;$discount=0;
            if(!empty($validated['customer_id'])){$customer=Customer::query()->where('tenant_id',$tenantId)->whereKey($validated['customer_id'])->first();if(!$customer)return response()->json(['status'=>'error','message'=>'Customer tidak berada pada tenant aktif.'],422);$discount=match($customer->tier){'vip'=>$subtotal*0.10,'gold'=>$subtotal*0.05,default=>0};}

            $total=max(0,$subtotal-$discount);
            $template = ReceiptTemplate::query()->where('tenant_id',$tenantId)->where('company_id',$companyId)->where('branch_id',$branchId)->first();
            $ppnRate=(float)($template?->ppn_rate ?? 11);
            $divisor=1+($ppnRate/100);
            $basePrice=$divisor>0 ? $total/$divisor : $total;
            $tax=$total-$basePrice;
            $netProfit=$basePrice-$totalCogs;
            $invoiceNumber='INV-'.date('Ymd').'-'.strtoupper(Str::random(5));
            foreach($processedItems as $processedItem){$product=Product::query()->where('tenant_id',$tenantId)->findOrFail($processedItem['product_id']);$this->inventory->issue($warehouse,$product,(float)$processedItem['quantity'],'pos_order',$invoiceNumber,'POS sale stock issue');}

            $order=Order::create(['tenant_id'=>$tenantId,'company_id'=>$companyId,'branch_id'=>$branchId,'location_id'=>$locationId,'user_id'=>$user->id,'shift_id'=>$activeShift->id,'customer_id'=>$customer?->id,'invoice_number'=>$invoiceNumber,'subtotal'=>$basePrice,'tax'=>$tax,'discount'=>$discount,'total'=>$total,'total_cogs'=>$totalCogs,'net_profit'=>$netProfit,'payment_method'=>$validated['payment_method'],'order_type'=>$validated['order_type'],'customer_name'=>$validated['customer_name']??null,'status'=>$validated['payment_method']==='cash'?'paid':'pending']);
            if($customer&&$validated['payment_method']==='cash')$customer->increment('points',(int)floor($total/10000));
            foreach($processedItems as $processedItem)$order->items()->create($processedItem);
            if($order->status==='paid')$this->accounting->postPaidOrder($order);
            $paymentUrl=null;
            if($validated['payment_method']!=='cash'){Config::$serverKey=config('midtrans.server_key');Config::$isProduction=config('midtrans.is_production');Config::$isSanitized=true;Config::$is3ds=true;$snapToken=Snap::getSnapToken(['transaction_details'=>['order_id'=>$order->invoice_number,'gross_amount'=>(int)$order->total],'customer_details'=>['first_name'=>'Pelanggan','last_name'=>'Cafe Menteng']]);$paymentUrl='https://app.sandbox.midtrans.com/snap/v3/redirection/'.$snapToken;}else$activeShift->increment('expected_ending_cash',$total);
            DB::commit();$order->load('items.product');$this->audit->record('created','pos.order',$order,null,$order->toArray());return response()->json(['status'=>'success','message'=>'Transaksi berhasil diproses','payment_url'=>$paymentUrl,'data'=>$order],201);
        }catch(ValidationException $e){DB::rollBack();return response()->json(['status'=>'error','message'=>'Transaksi tidak dapat diproses.','errors'=>$e->errors()],422);}catch(\Throwable $e){DB::rollBack();return response()->json(['status'=>'error','message'=>'Transaksi gagal: '.$e->getMessage()],500);}
    }

    public function history(Request $request){$orders=Order::with('items.product')->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->when($this->context->locationId()!==null,fn($query)=>$query->where('location_id',$this->context->locationId()))->orderByDesc('created_at')->paginate(min((int)$request->integer('per_page',50),100));return response()->json(['status'=>'success','data'=>$orders]);}
}
