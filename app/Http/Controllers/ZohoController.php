<?php

namespace App\Http\Controllers;

use App\Models\ZohoToken;
use App\Services\ZohoService;
use App\Services\ZohoSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZohoController extends Controller
{
    /**
     * Redirect the user to the Zoho OAuth authorization page.
     */
    public function redirect(ZohoService $zohoService)
    {
        return redirect()->away($zohoService->getAuthUrl());
    }

    /**
     * Handle the OAuth callback from Zoho and store the token.
     */
    public function callback(Request $request, ZohoService $zohoService)
    {
        try {
            $code = $request->input('code');

            if (!$code) {
                return redirect()->route('settings')
                    ->with('error', 'Zoho authorization failed: no authorization code received.');
            }

            $zohoService->handleCallback($code);

            return redirect()->route('settings')
                ->with('success', 'Zoho CRM connected successfully.');
        } catch (\Exception $e) {
            Log::error('Zoho OAuth callback error', ['error' => $e->getMessage()]);

            return redirect()->route('settings')
                ->with('error', 'Failed to connect Zoho CRM: ' . $e->getMessage());
        }
    }

    /**
     * Trigger a manual full sync of Zoho CRM data.
     */
    public function manualSync(ZohoSyncService $syncService)
    {
        try {
            $syncService->fullSync('manual');

            return redirect()->back()
                ->with('success', 'Zoho CRM data synced successfully.');
        } catch (\Exception $e) {
            Log::error('Zoho manual sync error', ['error' => $e->getMessage()]);

            return redirect()->back()
                ->with('error', 'Zoho sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Zoho CRM by removing all stored tokens.
     */
    public function disconnect()
    {
        try {
            ZohoToken::truncate();

            return redirect()->back()
                ->with('success', 'Zoho CRM disconnected successfully.');
        } catch (\Exception $e) {
            Log::error('Zoho disconnect error', ['error' => $e->getMessage()]);

            return redirect()->back()
                ->with('error', 'Failed to disconnect Zoho CRM: ' . $e->getMessage());
        }
    }
}
