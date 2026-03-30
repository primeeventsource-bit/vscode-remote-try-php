<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * GET /api/leads
     * List leads with optional filters: assigned_to, disposition, source
     */
    public function index(Request $request)
    {
        try {
            $query = Lead::query();

            if ($request->filled('assigned_to')) {
                $query->where('assigned_to', $request->input('assigned_to'));
            }

            if ($request->filled('disposition')) {
                $query->where('disposition', $request->input('disposition'));
            }

            if ($request->filled('source')) {
                $query->where('source', $request->input('source'));
            }

            $leads = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['leads' => $leads]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/leads
     * Create a new lead
     */
    public function store(Request $request)
    {
        try {
            $data = $request->only([
                'resort', 'owner_name', 'phone1', 'phone2',
                'city', 'st', 'zip', 'resort_location',
                'assigned_to', 'original_fronter', 'disposition',
                'transferred_to', 'source', 'callback_date',
            ]);

            $lead = Lead::create($data);

            return response()->json(['lead' => $lead], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/leads/import
     * Bulk CSV import of leads
     */
    public function import(Request $request)
    {
        try {
            $leads = $request->input('leads', []);

            if (empty($leads) || !is_array($leads)) {
                return response()->json(['error' => 'leads array is required'], 400);
            }

            $imported = 0;
            $errors = [];

            foreach ($leads as $index => $row) {
                try {
                    Lead::create([
                        'resort' => $row['resort'] ?? null,
                        'owner_name' => $row['owner_name'] ?? $row['ownerName'] ?? null,
                        'phone1' => $row['phone1'] ?? null,
                        'phone2' => $row['phone2'] ?? null,
                        'city' => $row['city'] ?? null,
                        'st' => $row['st'] ?? $row['state'] ?? null,
                        'zip' => $row['zip'] ?? null,
                        'resort_location' => $row['resort_location'] ?? $row['resortLocation'] ?? null,
                        'assigned_to' => $row['assigned_to'] ?? $row['assignedTo'] ?? null,
                        'original_fronter' => $row['original_fronter'] ?? $row['originalFronter'] ?? null,
                        'disposition' => $row['disposition'] ?? 'New',
                        'source' => $row['source'] ?? 'Import',
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$index}: " . $e->getMessage();
                }
            }

            return response()->json([
                'ok' => true,
                'imported' => $imported,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/leads/{id}
     * Update a lead
     */
    public function update(Request $request, $id)
    {
        try {
            $lead = Lead::find($id);

            if (!$lead) {
                return response()->json(['error' => 'Lead not found'], 404);
            }

            $data = $request->only([
                'resort', 'owner_name', 'phone1', 'phone2',
                'city', 'st', 'zip', 'resort_location',
                'assigned_to', 'original_fronter', 'disposition',
                'transferred_to', 'source', 'callback_date',
            ]);

            $lead->update($data);

            return response()->json(['lead' => $lead]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/leads/{id}/disposition
     * Set disposition: {disposition, transferredTo}
     */
    public function disposition(Request $request, $id)
    {
        try {
            $lead = Lead::find($id);

            if (!$lead) {
                return response()->json(['error' => 'Lead not found'], 404);
            }

            $lead->update([
                'disposition' => $request->input('disposition'),
                'transferred_to' => $request->input('transferredTo', $request->input('transferred_to')),
            ]);

            return response()->json(['lead' => $lead]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/leads/{id}/assign
     * Reassign lead: {assignedTo}
     */
    public function assign(Request $request, $id)
    {
        try {
            $lead = Lead::find($id);

            if (!$lead) {
                return response()->json(['error' => 'Lead not found'], 404);
            }

            $lead->update([
                'assigned_to' => $request->input('assignedTo', $request->input('assigned_to')),
            ]);

            return response()->json(['lead' => $lead]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/leads/{id}
     * Delete a lead
     */
    public function destroy($id)
    {
        try {
            $lead = Lead::find($id);

            if (!$lead) {
                return response()->json(['error' => 'Lead not found'], 404);
            }

            $lead->delete();

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
