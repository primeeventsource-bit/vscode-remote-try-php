<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query();
        $user = $request->user();

        // Non-admin users only see their own leads
        if (! $user->hasRole('master_admin', 'admin') && ! $user->hasPerm('view_all_leads')) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }
        if ($request->filled('disposition')) {
            $query->where('disposition', $request->input('disposition'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($leads);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'resort'           => 'nullable|string|max:255',
            'owner_name'       => 'required|string|max:255',
            'phone1'           => 'nullable|string|max:50',
            'phone2'           => 'nullable|string|max:50',
            'city'             => 'nullable|string|max:100',
            'st'               => 'nullable|string|max:10',
            'zip'              => 'nullable|string|max:20',
            'resort_location'  => 'nullable|string|max:255',
            'assigned_to'      => 'nullable|integer|exists:users,id',
            'original_fronter' => 'nullable|integer|exists:users,id',
            'disposition'      => 'nullable|string|max:100',
            'source'           => 'nullable|string|max:100',
            'callback_date'    => 'nullable|date',
        ]);

        $data['source'] = $data['source'] ?? 'manual';

        $lead = Lead::create($data);

        return response()->json(['lead' => $lead], 201);
    }

    public function import(Request $request)
    {
        $request->validate([
            'leads'              => 'required|array|min:1|max:5000',
            'leads.*.owner_name' => 'required|string|max:255',
            'leads.*.resort'     => 'nullable|string|max:255',
            'leads.*.phone1'     => 'nullable|string|max:50',
            'leads.*.phone2'     => 'nullable|string|max:50',
            'leads.*.city'       => 'nullable|string|max:100',
            'leads.*.st'         => 'nullable|string|max:10',
            'leads.*.zip'        => 'nullable|string|max:20',
        ]);

        $leads = $request->input('leads');
        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($leads, &$imported, &$errors) {
            foreach ($leads as $index => $row) {
                try {
                    Lead::create([
                        'resort'           => $row['resort'] ?? null,
                        'owner_name'       => $row['owner_name'] ?? $row['ownerName'] ?? null,
                        'phone1'           => $row['phone1'] ?? null,
                        'phone2'           => $row['phone2'] ?? null,
                        'city'             => $row['city'] ?? null,
                        'st'               => $row['st'] ?? $row['state'] ?? null,
                        'zip'              => $row['zip'] ?? null,
                        'resort_location'  => $row['resort_location'] ?? $row['resortLocation'] ?? null,
                        'assigned_to'      => $row['assigned_to'] ?? $row['assignedTo'] ?? null,
                        'original_fronter' => $row['original_fronter'] ?? $row['originalFronter'] ?? null,
                        'disposition'      => $row['disposition'] ?? 'New',
                        'source'           => $row['source'] ?? 'Import',
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$index}: Invalid data";
                }
            }
        });

        return response()->json([
            'ok'       => true,
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }

    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'resort'           => 'nullable|string|max:255',
            'owner_name'       => 'sometimes|string|max:255',
            'phone1'           => 'nullable|string|max:50',
            'phone2'           => 'nullable|string|max:50',
            'city'             => 'nullable|string|max:100',
            'st'               => 'nullable|string|max:10',
            'zip'              => 'nullable|string|max:20',
            'resort_location'  => 'nullable|string|max:255',
            'disposition'      => 'nullable|string|max:100',
            'callback_date'    => 'nullable|date',
            'source'           => 'nullable|string|max:100',
        ]);

        // Only admins can reassign leads
        if ($request->has('assigned_to') && $request->user()->hasRole('master_admin', 'admin', 'closer')) {
            $request->validate(['assigned_to' => 'nullable|integer|exists:users,id']);
            $data['assigned_to'] = $request->input('assigned_to');
        }

        $lead->update($data);

        return response()->json(['lead' => $lead->fresh()]);
    }

    public function disposition(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'disposition'     => 'required|string|max:100',
            'transferred_to'  => 'nullable|integer|exists:users,id',
        ]);

        $lead->update([
            'disposition'     => $data['disposition'],
            'transferred_to'  => $data['transferred_to'] ?? $lead->transferred_to,
        ]);

        return response()->json(['lead' => $lead->fresh()]);
    }

    public function assign(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'assigned_to' => 'required|integer|exists:users,id',
        ]);

        $lead->update(['assigned_to' => $data['assigned_to']]);

        return response()->json(['lead' => $lead->fresh()]);
    }

    public function destroy($id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json(['ok' => true]);
    }
}
