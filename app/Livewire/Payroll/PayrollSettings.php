<?php

namespace App\Livewire\Payroll;

use App\Models\FinanceAudit;
use App\Models\PayrollSettingModel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payroll Settings')]
class PayrollSettings extends Component
{
    public float $fronter_percent = 6.00;
    public float $closer_percent = 12.00;
    public float $admin_percent = 2.00;
    public float $processing_percent = 3.00;
    public float $reserve_percent = 3.00;
    public float $marketing_percent = 15.00;
    public bool $hold_enabled = true;
    public float $hold_percent = 10.00;
    public int $hold_days = 14;
    public bool $allow_admin_adjustments = true;
    public bool $auto_calculate = true;

    public function mount()
    {
        try {
            $this->fronter_percent = PayrollSettingModel::get('fronter_default_percent', 6.00);
            $this->closer_percent = PayrollSettingModel::get('closer_default_percent', 12.00);
            $this->admin_percent = PayrollSettingModel::get('admin_default_percent', 2.00);
            $this->processing_percent = PayrollSettingModel::get('processing_default_percent', 3.00);
            $this->reserve_percent = PayrollSettingModel::get('reserve_default_percent', 3.00);
            $this->marketing_percent = PayrollSettingModel::get('marketing_default_percent', 15.00);
            $this->hold_enabled = PayrollSettingModel::get('commission_hold_enabled', true);
            $this->hold_percent = PayrollSettingModel::get('commission_hold_percent', 10.00);
            $this->hold_days = PayrollSettingModel::get('commission_hold_days', 14);
            $this->allow_admin_adjustments = PayrollSettingModel::get('allow_admin_adjustments', true);
            $this->auto_calculate = PayrollSettingModel::get('auto_calculate_on_verified_charged', true);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function save()
    {
        $this->validate([
            'fronter_percent' => 'required|numeric|min:0|max:100',
            'closer_percent' => 'required|numeric|min:0|max:100',
            'admin_percent' => 'required|numeric|min:0|max:100',
            'processing_percent' => 'required|numeric|min:0|max:100',
            'reserve_percent' => 'required|numeric|min:0|max:100',
            'marketing_percent' => 'required|numeric|min:0|max:100',
            'hold_percent' => 'required|numeric|min:0|max:100',
            'hold_days' => 'required|integer|min:0|max:365',
        ]);

        $before = PayrollSettingModel::getDefaults();

        PayrollSettingModel::set('fronter_default_percent', $this->fronter_percent, 'decimal');
        PayrollSettingModel::set('closer_default_percent', $this->closer_percent, 'decimal');
        PayrollSettingModel::set('admin_default_percent', $this->admin_percent, 'decimal');
        PayrollSettingModel::set('processing_default_percent', $this->processing_percent, 'decimal');
        PayrollSettingModel::set('reserve_default_percent', $this->reserve_percent, 'decimal');
        PayrollSettingModel::set('marketing_default_percent', $this->marketing_percent, 'decimal');
        PayrollSettingModel::set('commission_hold_enabled', $this->hold_enabled ? 'true' : 'false', 'boolean');
        PayrollSettingModel::set('commission_hold_percent', $this->hold_percent, 'decimal');
        PayrollSettingModel::set('commission_hold_days', $this->hold_days, 'integer');
        PayrollSettingModel::set('allow_admin_adjustments', $this->allow_admin_adjustments ? 'true' : 'false', 'boolean');
        PayrollSettingModel::set('auto_calculate_on_verified_charged', $this->auto_calculate ? 'true' : 'false', 'boolean');

        FinanceAudit::record('PayrollSettings', 0, 'settings_updated', $before, PayrollSettingModel::getDefaults(), 'Global payroll settings updated');

        session()->flash('payroll_success', 'Payroll settings saved. Changes apply to future deals only.');
    }

    public function resetDefaults()
    {
        $this->fronter_percent = 6.00;
        $this->closer_percent = 12.00;
        $this->admin_percent = 2.00;
        $this->processing_percent = 3.00;
        $this->reserve_percent = 3.00;
        $this->marketing_percent = 15.00;
        $this->hold_enabled = true;
        $this->hold_percent = 10.00;
        $this->hold_days = 14;
        $this->allow_admin_adjustments = true;
        $this->auto_calculate = true;
    }

    public function render()
    {
        if (!auth()->user()->hasRole('master_admin')) abort(403);

        $totalPct = $this->fronter_percent + $this->closer_percent + $this->admin_percent + $this->processing_percent + $this->reserve_percent + $this->marketing_percent;
        $companyRetained = round(100 - $totalPct, 2);

        return view('livewire.payroll.settings', compact('totalPct', 'companyRetained'));
    }
}
