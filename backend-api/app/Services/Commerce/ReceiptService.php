<?php

namespace App\Services\Commerce;

use App\Enums\PaymentStatus;
use App\Enums\ReceiptType;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceiptService
{
    public function issue(Payment $source): Receipt
    {
        return DB::transaction(function () use ($source) {
            $payment = Payment::lockForUpdate()->findOrFail($source->id);
            if ($receipt = Receipt::where('payment_id', $payment->id)->first()) {
                return $receipt;
            }
            abort_unless($payment->status === PaymentStatus::PAID && $payment->paid_at && $payment->is_demo, 409, 'Paiement DEMO réussi requis.');
            $order = $payment->order;
            abort_unless($order->is_demo && $payment->currency_code === $order->currency_code && $payment->amount_minor === $order->total_minor, 409);
            $r = $order->kind === 'rental' ? $order->reservation : null;
            $transaction = ['order_reference' => $order->reference, 'order_id' => (string) $order->id, 'payment_reference' => $payment->reference, 'paid_at' => $payment->paid_at->toISOString(), 'conditions_version' => $order->conditions_version, 'price_offer_id' => $order->price_offer_id, 'timezone' => $r?->shop_timezone ?? ($order->seller_snapshot['timezone'] ?? 'UTC')];
            if ($r) {
                $transaction += ['reservation_id' => (string) $r->id, 'reservation_reference' => $r->reference, 'starts_at' => $r->starts_at->toISOString(), 'ends_at' => $r->ends_at->toISOString(), 'days' => $r->billable_days, 'daily_price_minor' => $r->daily_price_minor];
            }

            return Receipt::create(['reference' => 'BM-RCP-'.now()->year.'-'.Str::ulid(), 'payment_id' => $payment->id, 'order_id' => $order->id, 'user_id' => $order->user_id, 'shop_id' => $order->shop_id, 'type' => $r ? ReceiptType::RENTAL : ReceiptType::SALE,
                'currency_code' => $order->currency_code, 'minor_unit' => $order->minor_unit, 'subtotal_minor' => $order->subtotal_minor, 'fees_minor' => $order->fees_minor, 'total_minor' => $order->total_minor, 'payment_method' => $payment->method->value, 'payment_status' => $payment->status->value, 'is_demo' => $payment->is_demo, 'issued_at' => now(),
                // Legacy records have no historical buyer snapshot. Never invent one from today's profile.
                'buyer_snapshot' => $order->buyer_snapshot ?? ['name' => 'Client - coordonnées historiques non enregistrées'],
                'seller_snapshot' => $order->seller_snapshot, 'vehicle_snapshot' => $order->vehicle_snapshot, 'transaction_snapshot' => $transaction]);
        }, 3);
    }
}
