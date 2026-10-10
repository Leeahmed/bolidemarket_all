<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminActionRequest;
use App\Http\Requests\Admin\AdminListRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Services\AdminModerationService;
use App\Services\AdminReadService;
use App\Services\Commerce\ReceiptPdfService;
use App\Support\DemoMode;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private AdminReadService $read) {}

    private function json(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function index(AdminListRequest $r)
    {
        $p = $r->validated();
        $type = $r->route('type');
        $page = $this->read->filtered($type, $p)->orderByDesc('id')->paginate($p['per_page'] ?? 20)->withQueryString();

        return $this->json(['data' => $page->getCollection()->map(fn ($record) => $this->read->dto($type, $record, $r)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()]]);
    }

    public function show(Request $r)
    {
        $type = $r->route('type');
        $q = $this->read->query($type);
        $record = $type === 'receipts' ? $q->where('reference', $r->route('id'))->firstOrFail() : $q->findOrFail($r->route('id'));

        return $this->json(['data' => $this->read->detail($type, $record, $r)]);
    }

    public function action(AdminActionRequest $r, AdminModerationService $service)
    {
        $service->act($r->user(), $r->route('type'), (int) $r->route('id'), $r->validated('action'), $r->validated('reason'));

        return $this->show($r);
    }

    public function dashboard(Request $r)
    {
        $p = $r->validate(['period' => 'sometimes|in:7,30,90,365', 'scope' => 'sometimes|in:all,demo,real']);
        $p['scope'] ??= DemoMode::enabled() ? 'demo' : 'real';

        return $this->json(['data' => $this->read->dashboard($p, $r)]);
    }

    public function search(Request $r)
    {
        $p = $r->validate(['q' => 'required|string|min:2|max:120']);
        $results = [];
        foreach (['users', 'merchants', 'shops', 'vehicles', 'reservations', 'orders', 'receipts'] as $type) {
            $records = $this->read->filtered($type, $p)->orderByDesc('id')->limit(5)->get();
            foreach ($records as $record) {
                $results[] = ['type' => $type, 'id' => (string) $record->id, 'reference' => $record->reference,
                    'label' => $record->reference ?? $record->title ?? $record->display_name ?? $record->name ?? $record->email];
            }
        }

        return $this->json(['data' => $results]);
    }

    public function lookup(Request $r)
    {
        $p = $r->validate(['type' => 'required|in:cities,shops,merchants', 'q' => 'sometimes|string|max:120', 'id' => 'sometimes|integer|min:1', 'country_code' => 'sometimes|exists:countries,code']);
        $type = $p['type'];
        $q = $type === 'cities' ? City::query() : $this->read->filtered($type, array_diff_key($p, ['id' => 1]));
        if ($type === 'cities') {
            if (isset($p['country_code'])) {
                $q->where('country_code', $p['country_code']);
            }
            if (isset($p['q'])) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $p['q']).'%';
                $q->where('name', 'like', $term);
            }
        }
        if (isset($p['id'])) {
            $q->whereKey($p['id']);
        }
        $records = $q->orderByDesc('id')->limit(25)->get();

        return $this->json(['data' => $records->map(fn ($row) => ['id' => (string) $row->id, 'name' => $row->display_name ?? $row->name])]);
    }

    public function settings()
    {
        return $this->json(['data' => ['demo_mode' => DemoMode::enabled(), 'payments' => 'DEMO uniquement',
            'countries' => Country::orderBy('name')->get(['code', 'name', 'active', 'currency_code']),
            'currencies' => Currency::orderBy('code')->get(['code', 'minor_unit', 'active']),
            'admin_promotion' => 'Aucune promotion publique. Création contrôlée par les outils serveur.',
            'retention' => 'Suspension uniquement. Transactions et reçus historiques conservés.']]);
    }

    public function pdf(Request $r, ReceiptPdfService $pdf)
    {
        $r->validate(['disposition' => 'sometimes|in:inline,attachment']);
        $receipt = $this->read->query('receipts')->where('reference', $r->route('id'))->firstOrFail();

        return response($pdf->render($receipt), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => $r->query('disposition', 'attachment').'; filename="BolideMarket_'.$receipt->reference.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
