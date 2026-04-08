<?php

namespace App\Livewire\Finance;

use App\Models\FinanceSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Finance Settings')]
class FinanceSettings extends Component
{
    // General
    public string $default_date_range = '30d';
    public string $currency_format = 'USD';
    public string $timezone = 'America/New_York';

    // Profitability
    public bool $include_reserve_holds = true;
    public bool $include_reserve_releases = true;
    public bool $include_adjustments = true;

    // Import
    public int $max_upload_size = 20480;
    public bool $import_confirmation_required = true;
    public float $low_confidence_threshold = 0.7;
    public string $duplicate_handling = 'skip'; // skip, overwrite, flag

    // Due date warnings
    public int $cb_due_soon_days = 7;
    public int $cb_overdue_days = 0;

    public function mount()
    {
        try {
            $settings = FinanceSetting::getMany([
                'general.default_date_range' => '30d',
                'general.currency_format' => 'USD',
                'general.timezone' => 'America/New_York',
                'profitability.include_reserve_holds' => true,
                'profitability.include_reserve_releases' => true,
                'profitability.include_adjustments' => true,
                'import.max_upload_size' => 20480,
                'import.confirmation_required' => true,
                'import.low_confidence_threshold' => 0.7,
                'import.duplicate_handling' => 'skip',
                'chargeback.due_soon_days' => 7,
                'chargeback.overdue_days' => 0,
            ]);

            $this->default_date_range = $settings['general.default_date_range'];
            $this->currency_format = $settings['general.currency_format'];
            $this->timezone = $settings['general.timezone'];
            $this->include_reserve_holds = (bool) $settings['profitability.include_reserve_holds'];
            $this->include_reserve_releases = (bool) $settings['profitability.include_reserve_releases'];
            $this->include_adjustments = (bool) $settings['profitability.include_adjustments'];
            $this->max_upload_size = (int) $settings['import.max_upload_size'];
            $this->import_confirmation_required = (bool) $settings['import.confirmation_required'];
            $this->low_confidence_threshold = (float) $settings['import.low_confidence_threshold'];
            $this->duplicate_handling = $settings['import.duplicate_handling'];
            $this->cb_due_soon_days = (int) $settings['chargeback.due_soon_days'];
            $this->cb_overdue_days = (int) $settings['chargeback.overdue_days'];
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function save()
    {
        FinanceSetting::set('general.default_date_range', $this->default_date_range);
        FinanceSetting::set('general.currency_format', $this->currency_format);
        FinanceSetting::set('general.timezone', $this->timezone);
        FinanceSetting::set('profitability.include_reserve_holds', $this->include_reserve_holds);
        FinanceSetting::set('profitability.include_reserve_releases', $this->include_reserve_releases);
        FinanceSetting::set('profitability.include_adjustments', $this->include_adjustments);
        FinanceSetting::set('import.max_upload_size', $this->max_upload_size);
        FinanceSetting::set('import.confirmation_required', $this->import_confirmation_required);
        FinanceSetting::set('import.low_confidence_threshold', $this->low_confidence_threshold);
        FinanceSetting::set('import.duplicate_handling', $this->duplicate_handling);
        FinanceSetting::set('chargeback.due_soon_days', $this->cb_due_soon_days);
        FinanceSetting::set('chargeback.overdue_days', $this->cb_overdue_days);

        session()->flash('finance_success', 'Finance settings saved successfully.');
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin')) abort(403);
        return view('livewire.finance.settings');
    }
}
