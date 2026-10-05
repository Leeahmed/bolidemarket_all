<?php

namespace App\Services\Commerce;

use App\Enums\DemoPaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

class DemoPaymentService
{
    public function pay(Order $order, User $actor): Payment
    {
        abort_unless(app()->environment(['local', 'testing']) && $order->is_demo, 403);
        // Called only inside the allocation transaction. No network or financial input.
        DemoPaymentMethod::from($order->payment_method_demo->value);
        $payment = Payment::firstOrCreate(['order_id' => $order->id], [
            'reference' => 'BM-PAY-'.Str::ulid(), 'provider' => 'demo', 'method' => $order->payment_method_demo,
            'amount_minor' => $order->total_minor, 'currency_code' => $order->currency_code, 'minor_unit' => $order->minor_unit,
            'status' => PaymentStatus::PAID, 'paid_at' => now(), 'is_demo' => true,
        ]);
        if ($payment->wasRecentlyCreated) {
            CommerceAudit::record($actor, $payment, 'demo_paid');
        }

        app(ReceiptService::class)->issue($payment);
        $order->unsetRelation('receipt');

        return $payment;
    }
}
