<?php

namespace App\Http\Controllers;

use App\Models\Chargeback;
use App\Models\ChargebackEvent;
use App\Services\ChargebackAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargebackController extends Controller
{
    public function __construct(private ChargebackAnalyticsService $analytics)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        [$start, $end] = $this->analytics->parsePeriod(
            $request->string('period', 'last_30_days')->toString(),
            $request->string('start_date')->toString(),
            $request->string('end_date')->toString(),
        );
        [$prevStart, $prevEnd] = $this->analytics->previousPeriod($start, $end);

        return response()->json($this->analytics->summary($request->all(), $start, $end, $prevStart, $prevEnd));
    }

    public function trends(Request $request): JsonResponse
    {
        [$start, $end] = $this->analytics->parsePeriod(
            $request->string('period', 'last_30_days')->toString(),
            $request->string('start_date')->toString(),
            $request->string('end_date')->toString(),
        );

        return response()->json($this->analytics->trends($request->all(), $start, $end));
    }

    public function breakdowns(Request $request): JsonResponse
    {
        [$start, $end] = $this->analytics->parsePeriod(
            $request->string('period', 'last_30_days')->toString(),
            $request->string('start_date')->toString(),
            $request->string('end_date')->toString(),
        );

        return response()->json($this->analytics->breakdowns($request->all(), $start, $end));
    }

    public function index(Request $request): JsonResponse
    {
        $query = Chargeback::query()->with(['processor', 'merchantAccount', 'salesRep', 'deal']);

        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $query->where(function ($q) use ($s): void {
                $q->where('dispute_reference_number', 'like', "%{$s}%")
                    ->orWhere('reason_code', 'like', "%{$s}%")
                    ->orWhere('reason_description', 'like', "%{$s}%");
            });
        }

        foreach (['processor_id', 'sales_rep_id', 'merchant_account_id', 'status', 'reason_code', 'card_brand', 'payment_method'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('dispute_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $items = $query->orderByDesc('dispute_date')->paginate((int) $request->integer('per_page', 25));

        return response()->json($items);
    }

    public function show(int $id): JsonResponse
    {
        $row = Chargeback::with(['processor', 'merchantAccount', 'salesRep', 'deal', 'events.performer', 'documents.uploader'])->findOrFail($id);

        return response()->json($row);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deal_id' => 'nullable|integer',
            'processor_id' => 'nullable|integer',
            'merchant_account_id' => 'nullable|integer',
            'sales_rep_id' => 'nullable|integer',
            'dispute_reference_number' => 'nullable|string|max:255',
            'chargeback_amount' => 'required|numeric|min:0',
            'original_transaction_amount' => 'nullable|numeric|min:0',
            'status' => 'required|string|max:40',
            'outcome' => 'nullable|string|max:40',
            'reason_code' => 'nullable|string|max:80',
            'reason_description' => 'nullable|string|max:255',
            'card_brand' => 'nullable|string|max:40',
            'payment_method' => 'nullable|string|max:40',
            'dispute_date' => 'nullable|date',
            'deadline_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $row = Chargeback::create($data);

        return response()->json($row, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = Chargeback::findOrFail($id);
        $oldStatus = $row->status;

        $data = $request->validate([
            'status' => 'nullable|string|max:40',
            'outcome' => 'nullable|string|max:40',
            'reason_code' => 'nullable|string|max:80',
            'reason_description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'response_submitted_at' => 'nullable|date',
            'resolved_at' => 'nullable|date',
        ]);

        $row->update($data);

        if (array_key_exists('status', $data) && $data['status'] !== $oldStatus) {
            ChargebackEvent::create([
                'chargeback_id' => $row->id,
                'event_type' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $data['status'],
                'event_date' => now(),
                'performed_by' => auth()->id(),
                'notes' => 'Status updated via API',
            ]);
        }

        return response()->json($row->fresh(['events']));
    }

    public function storeEvent(Request $request, int $id): JsonResponse
    {
        $chargeback = Chargeback::findOrFail($id);

        $data = $request->validate([
            'event_type' => 'required|string|max:80',
            'old_status' => 'nullable|string|max:40',
            'new_status' => 'nullable|string|max:40',
            'event_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $event = ChargebackEvent::create(array_merge($data, [
            'chargeback_id' => $chargeback->id,
            'performed_by' => auth()->id(),
            'event_date' => $data['event_date'] ?? now(),
        ]));

        return response()->json($event, 201);
    }

    public function filterOptions(): JsonResponse
    {
        return response()->json($this->analytics->filterOptions());
    }
}
