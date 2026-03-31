<?php
namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public string $crmName = 'PRIME CRM';
    public string $theme = 'light';
    public array $dealStatuses = [];
    public string $newStatusLabel = '';
    public string $newStatusColor = '#3b82f6';

    public function mount()
    {
        $this->crmName = $this->getSetting('crmName', 'PRIME CRM');
        $this->theme = $this->getSetting('theme', 'light');
        $this->dealStatuses = $this->getSetting('dealStatuses', [
            ['id' => 'pending_admin', 'label' => 'Pending Admin', 'color' => '#f59e0b'],
            ['id' => 'in_verification', 'label' => 'In Verification', 'color' => '#3b82f6'],
            ['id' => 'charged', 'label' => 'Charged', 'color' => '#10b981'],
            ['id' => 'chargeback', 'label' => 'Chargeback', 'color' => '#ef4444'],
            ['id' => 'cancelled', 'label' => 'Cancelled', 'color' => '#6b7280'],
        ]);
    }

    private function getSetting($key, $default)
    {
        $row = DB::table('crm_settings')->where('key', $key)->first();
        return $row ? json_decode($row->value, true) : $default;
    }

    private function saveSetting($key, $value)
    {
        DB::table('crm_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
    }

    public function saveName() { $this->saveSetting('crmName', $this->crmName); }
    public function setTheme($t) { $this->theme = $t; $this->saveSetting('theme', $t); }

    public function addStatus()
    {
        if (!$this->newStatusLabel) return;
        $this->dealStatuses[] = ['id' => str_replace(' ', '_', strtolower($this->newStatusLabel)), 'label' => $this->newStatusLabel, 'color' => $this->newStatusColor];
        $this->saveSetting('dealStatuses', $this->dealStatuses);
        $this->newStatusLabel = '';
        $this->newStatusColor = '#3b82f6';
    }

    public function removeStatus($idx)
    {
        $core = ['pending_admin', 'charged', 'chargeback', 'cancelled'];
        if (in_array($this->dealStatuses[$idx]['id'] ?? '', $core)) return;
        array_splice($this->dealStatuses, $idx, 1);
        $this->saveSetting('dealStatuses', $this->dealStatuses);
    }

    public function render() { return view('livewire.settings'); }
}
