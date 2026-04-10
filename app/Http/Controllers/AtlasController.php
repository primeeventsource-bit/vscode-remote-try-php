<?php

namespace App\Http\Controllers;

use App\Models\AtlasLead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AtlasController extends Controller
{
    public function exportCSV(Request $request): StreamedResponse
    {
        $leads = AtlasLead::query()
            ->when($request->status && $request->status !== 'ALL', fn($q) => $q->where('status', $request->status))
            ->when($request->county, fn($q) => $q->where('county', $request->county))
            ->orderByDesc('created_at')
            ->get();

        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Name', 'Resort', 'Date', 'Address', 'City', 'State', 'Zip', 'Status',
                'Existing Phone', 'Phone 1', 'Phone 1 Type', 'Phone 2', 'Phone 2 Type',
                'Phone 3', 'Phone 3 Type', 'Phone 4', 'Phone 4 Type', 'Phone 5', 'Phone 5 Type',
                'Confidence', 'Email 1', 'Email 2', 'Email 3', 'Source', 'Traced At',
            ]);

            foreach ($leads as $l) {
                fputcsv($out, [
                    $l->grantee, $l->grantor, $l->deed_date?->format('m/d/Y'),
                    $l->address, $l->city, $l->state, $l->zip, $l->status,
                    $l->existing_phone,
                    $l->phone_1, $l->phone_1_type, $l->phone_2, $l->phone_2_type,
                    $l->phone_3, $l->phone_3_type, $l->phone_4, $l->phone_4_type,
                    $l->phone_5, $l->phone_5_type,
                    $l->phone_confidence, $l->email_1, $l->email_2, $l->email_3,
                    $l->source, $l->traced_at?->format('m/d/Y H:i'),
                ]);
            }

            fclose($out);
        }, 'atlas-leads-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
