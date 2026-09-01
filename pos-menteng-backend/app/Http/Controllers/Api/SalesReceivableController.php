<?php
namespace App\Http\Controllers\Api;
use App\Domain\Sales\Services\SalesReceivableService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
class SalesReceivableController extends Controller{
 public function __construct(private readonly SalesReceivableService $service){}
 public function index():JsonResponse{return response()->json(['status'=>'success','data'=>$this->service->invoices()]);}
 public function aging():JsonResponse{return response()->json(['status'=>'success','data'=>$this->service->aging()]);}
}
