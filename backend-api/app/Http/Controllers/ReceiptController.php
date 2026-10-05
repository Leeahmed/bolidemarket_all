<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\PaginationRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Services\Commerce\ReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    private function query(Request $r)
    {
        return $r->is('api/v1/merchant/*') ? Receipt::forMerchant($r->user()) : Receipt::forCustomer($r->user());
    }

    private function record(Request $r, string $reference): Receipt
    {
        $receipt = $this->query($r)->where('reference', $reference)->firstOrFail();
        Gate::authorize($r->is('api/v1/merchant/*') ? 'manage' : 'view', $receipt);

        return $receipt;
    }

    public function index(PaginationRequest $r)
    {
        $input = $r->validate(['shop_id' => 'sometimes|integer|min:1', 'type' => 'sometimes|in:sale,rental']);
        $q = $this->query($r);
        if (isset($input['shop_id'])) {
            $q->where('shop_id', $input['shop_id']);
        }if (isset($input['type'])) {
            $q->where('type', $input['type']);
        }

        return ReceiptResource::collection($q->orderByDesc('id')->paginate($r->pageSize()))->response()->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $r, string $reference)
    {
        return (new ReceiptResource($this->record($r, $reference)))->response()->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Request $r, string $reference, ReceiptPdfService $pdf)
    {
        $receipt = $this->record($r, $reference);
        $r->validate(['disposition' => 'sometimes|in:inline,attachment']);

        return response($pdf->render($receipt), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($r->query('disposition', 'attachment')).'; filename="BolideMarket_'.$receipt->reference.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
