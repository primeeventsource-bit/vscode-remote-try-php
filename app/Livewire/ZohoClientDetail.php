<?php

namespace App\Livewire;

use App\Models\ZohoClient;
use App\Models\ZohoClientNote;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Zoho Client Detail')]
class ZohoClientDetail extends Component
{
    public int $clientId;
    public string $noteBody = '';

    public function mount($client)
    {
        $this->clientId = is_numeric($client) ? (int) $client : $client->id;
    }

    public function addNote()
    {
        if (!$this->noteBody) return;

        try {
            ZohoClientNote::create([
                'zoho_client_id' => $this->clientId,
                'user_id' => auth()->id(),
                'body' => $this->noteBody,
            ]);
            $this->noteBody = '';
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function render()
    {
        $client = null;
        $deals = collect();
        $activities = collect();
        $notes = collect();
        $clientNotes = collect();

        try {
            $client = ZohoClient::findOrFail($this->clientId);
            $deals = $client->deals()->orderByDesc('created_at')->get();
            $activities = $client->activities()->orderByDesc('activity_date')->limit(20)->get();
            $notes = $client->zohoNotes()->orderByDesc('created_at')->limit(20)->get();
            $clientNotes = $client->clientNotes()->with('user')->orderByDesc('created_at')->get();
        } catch (\Throwable $e) {
            report($e);
            if (!$client) abort(404);
        }

        $users = \App\Models\User::all()->keyBy('id');

        return view('livewire.zoho-client-detail', compact('client', 'deals', 'activities', 'notes', 'clientNotes', 'users'));
    }
}
