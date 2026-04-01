<?php

namespace App\Livewire;

use App\Models\Chargeback;
use App\Models\ChargebackEvent;
use App\Models\MerchantAccount;
use App\Models\Processor;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Chargebacks')]
class Chargebacks extends Component
{
    use WithPagination;

    public string $search = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $processorId = '';
    public string $salesRepId = '';
    public string $merchantAccountId = '';
    public string $status = '';
    public string $reasonCode = '';
    public string $cardBrand = '';
    public string $paymentMethod = '';

    public ?int $selectedId = null;
    public string $newNote = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'processorId' => ['except' => ''],
        'salesRepId' => ['except' => ''],
        'merchantAccountId' => ['except' => ''],
        'status' => ['except' => ''],
        'reasonCode' => ['except' => ''],
        'cardBrand' => ['except' => ''],
        'paymentMethod' => ['except' => ''],
    ];

    public function updating($name): void
    {
        if (in_array($name, ['search', 'startDate', 'endDate', 'processorId', 'salesRepId', 'merchantAccountId', 'status', 'reasonCode', 'cardBrand', 'paymentMethod'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->startDate = '';
        $this->endDate = '';
        $this->processorId = '';
        $this->salesRepId = '';
        $this->merchantAccountId = '';
        $this->status = '';
        $this->reasonCode = '';
        $this->cardBrand = '';
        $this->paymentMethod = '';
    }

    public function openDetail(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
        $this->newNote = '';
    }

    public function addNote(): void
    {
        if (!$this->selectedId || trim($this->newNote) === '') {
            return;
        }

        ChargebackEvent::create([
            'chargeback_id' => $this->selectedId,
            'event_type' => 'note',
            'event_date' => now(),
            'performed_by' => auth()->id(),
            'notes' => trim($this->newNote),
        ]);

        $this->newNote = '';
    }

    public function updateStatus(string $status): void
    {
        if (!$this->selectedId) {
            return;
        }

        $row = Chargeback::find($this->selectedId);
        if (!$row) {
            return;
        }

        $old = $row->status;
        $row->update([
            'status' => $status,
            'outcome' => in_array($status, ['won', 'lost', 'refunded', 'prevented'], true) ? $status : $row->outcome,
            'resolved_at' => in_array($status, ['won', 'lost', 'refunded', 'prevented'], true) ? now() : $row->resolved_at,
        ]);

        ChargebackEvent::create([
            'chargeback_id' => $row->id,
            'event_type' => 'status_changed',
            'old_status' => $old,
            'new_status' => $status,
            'event_date' => now(),
            'performed_by' => auth()->id(),
            'notes' => 'Status changed from chargeback detail panel',
        ]);
    }

    public function render()
    {
        $query = Chargeback::query()->with(['processor', 'merchantAccount', 'salesRep', 'deal']);

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s): void {
                $q->where('dispute_reference_number', 'like', "%{$s}%")
                    ->orWhere('reason_code', 'like', "%{$s}%")
                    ->orWhere('reason_description', 'like', "%{$s}%");
            });
        }

        foreach ([
            'processorId' => 'processor_id',
            'salesRepId' => 'sales_rep_id',
            'merchantAccountId' => 'merchant_account_id',
            'status' => 'status',
            'reasonCode' => 'reason_code',
            'cardBrand' => 'card_brand',
            'paymentMethod' => 'payment_method',
        ] as $prop => $col) {
            if ($this->{$prop} !== '') {
                $query->where($col, $this->{$prop});
            }
        }

        if ($this->startDate !== '' && $this->endDate !== '') {
            $query->whereBetween('dispute_date', [$this->startDate, $this->endDate]);
        }

        $rows = $query->orderByDesc('dispute_date')->paginate(25);

        $selected = $this->selectedId
            ? Chargeback::with(['events.performer', 'documents.uploader', 'processor', 'merchantAccount', 'salesRep', 'deal'])->find($this->selectedId)
            : null;

        return view('livewire.chargebacks', [
            'rows' => $rows,
            'selected' => $selected,
            'processors' => Processor::query()->orderBy('name')->get(),
            'salesReps' => User::query()->orderBy('name')->get(),
            'merchantAccounts' => MerchantAccount::query()->orderBy('name')->get(),
            'statuses' => Chargeback::query()->select('status')->distinct()->pluck('status')->filter()->values(),
            'reasonCodes' => Chargeback::query()->select('reason_code')->distinct()->pluck('reason_code')->filter()->values(),
            'cardBrands' => Chargeback::query()->select('card_brand')->distinct()->pluck('card_brand')->filter()->values(),
            'paymentMethods' => Chargeback::query()->select('payment_method')->distinct()->pluck('payment_method')->filter()->values(),
        ]);
    }
}
