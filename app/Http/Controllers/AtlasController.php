<?php

namespace App\Http\Controllers;

use App\Models\AtlasLead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AtlasController extends Controller
{
    public function exportCSV(Request $request): StreamedResponse
    {
        $query = AtlasLead::query()->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }
        if ($request->filled('county')) {
            $query->where('county', $request->county);
        }

        $leads = $query->get();

        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Grantee', 'Grantor', 'Date', 'Address', 'County', 'State', 'Status', 'Phone1', 'Phone2', 'Phone3', 'Confidence', 'Instrument', 'Source']);

            foreach ($leads as $lead) {
                fputcsv($out, [
                    $lead->grantee,
                    $lead->grantor,
                    $lead->deed_date?->format('Y-m-d'),
                    $lead->address,
                    $lead->county,
                    $lead->state,
                    $lead->status,
                    $lead->phone_1,
                    $lead->phone_2,
                    $lead->phone_3,
                    $lead->phone_confidence,
                    $lead->instrument,
                    $lead->source,
                ]);
            }

            fclose($out);
        }, 'atlas-leads-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
