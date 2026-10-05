<?php

namespace App\Services\Commerce;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdfService
{
    public function html(Receipt $receipt): string
    {
        $logo = resource_path('receipts/logo.jpg');

        return view('receipts.pdf', ['r' => $receipt, 'buyer' => $receipt->buyer_snapshot, 'seller' => $receipt->seller_snapshot, 'vehicle' => $receipt->vehicle_snapshot, 'tx' => $receipt->transaction_snapshot, 'logo' => is_file($logo) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logo)) : null, 'money' => fn ($v) => self::money((string) $v, $receipt->minor_unit, $receipt->currency_code)])->render();
    }

    public static function money(string $minor, int $unit, string $currency): string
    {
        $s = str_pad($minor, $unit + 1, '0', STR_PAD_LEFT);
        $whole = $unit ? substr($s, 0, -$unit) : $s;

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $whole).($unit ? ','.substr($s, -$unit) : '').' '.(['XOF' => 'FCFA', 'EUR' => '€', 'USD' => '$', 'CAD' => 'CA$'][$currency] ?? $currency);
    }

    public function render(Receipt $receipt): string
    {
        $pdf = Pdf::loadHTML($this->html($receipt))->setPaper('a4', 'portrait')->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => resource_path('receipts'), 'isFontSubsettingEnabled' => true]);
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(480, 807, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.3, 0.3, 0.3]);

        return $pdf->output();
    }
}
