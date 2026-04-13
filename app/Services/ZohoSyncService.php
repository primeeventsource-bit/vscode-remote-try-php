<?php

namespace App\Services;

use App\Models\ZohoActivity;
use App\Models\ZohoClient;
use App\Models\ZohoDeal;
use App\Models\ZohoNote;
use App\Models\ZohoSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ZohoSyncService
{
    protected ZohoService $zohoService;

    public function __construct(ZohoService $zohoService)
    {
        $this->zohoService = $zohoService;
    }

    /**
     * Run a full sync of all Zoho CRM data.
     */
    public function fullSync(string $triggeredBy = 'system'): void
    {
        $this->syncContacts($triggeredBy);
        $this->syncDeals($triggeredBy);
        $this->syncActivities($triggeredBy);
        $this->syncNotes($triggeredBy);
    }

    /**
     * Sync contacts from Zoho CRM into the zoho_clients table.
     */
    public function syncContacts(string $triggeredBy = 'system'): ZohoSyncLog
    {
        $log = ZohoSyncLog::create([
            'sync_type'    => 'contacts',
            'status'       => 'running',
            'started_at'   => Carbon::now(),
            'triggered_by' => $triggeredBy,
        ]);

        $created = 0;
        $updated = 0;
        $failed  = 0;

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->zohoService->apiGet('Contacts', [
                    'page'     => $page,
                    'per_page' => 200,
                ]);

                $records = $response['data'] ?? [];

                if (empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    try {
                        $existing = ZohoClient::where('zoho_id', $record['id'])->first();

                        $attributes = [
                            'zoho_id'         => $record['id'],
                            'first_name'      => $record['First_Name'] ?? null,
                            'last_name'       => $record['Last_Name'] ?? null,
                            'email'           => $record['Email'] ?? null,
                            'phone'           => $record['Phone'] ?? null,
                            'mobile'          => $record['Mobile'] ?? null,
                            'account_name'    => $record['Account_Name']['name'] ?? ($record['Account_Name'] ?? null),
                            'title'           => $record['Title'] ?? null,
                            'department'      => $record['Department'] ?? null,
                            'mailing_address' => $record['Mailing_Street'] ?? null,
                            'mailing_city'    => $record['Mailing_City'] ?? null,
                            'mailing_state'   => $record['Mailing_State'] ?? null,
                            'mailing_zip'     => $record['Mailing_Zip'] ?? null,
                            'mailing_country' => $record['Mailing_Country'] ?? null,
                            'lead_source'     => $record['Lead_Source'] ?? null,
                            'contact_owner'   => $record['Owner']['name'] ?? ($record['Owner'] ?? null),
                            'status'          => 'active',
                            'last_synced_at'  => Carbon::now(),
                            'raw_data'        => $record,
                        ];

                        if ($existing) {
                            $existing->update($attributes);
                            $updated++;
                        } else {
                            ZohoClient::create($attributes);
                            $created++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Zoho contact sync: failed to process record', [
                            'zoho_id' => $record['id'] ?? 'unknown',
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

                $hasMore = ($response['info']['more_records'] ?? false) === true;
                $page++;
            }

            $log->update([
                'status'          => 'completed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'completed_at'    => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Zoho contacts sync failed', ['error' => $e->getMessage()]);

            $log->update([
                'status'          => 'failed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'error_message'   => $e->getMessage(),
                'completed_at'    => Carbon::now(),
            ]);
        }

        return $log;
    }

    /**
     * Sync deals from Zoho CRM into the zoho_deals table.
     */
    public function syncDeals(string $triggeredBy = 'system'): ZohoSyncLog
    {
        $log = ZohoSyncLog::create([
            'sync_type'    => 'deals',
            'status'       => 'running',
            'started_at'   => Carbon::now(),
            'triggered_by' => $triggeredBy,
        ]);

        $created = 0;
        $updated = 0;
        $failed  = 0;

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->zohoService->apiGet('Deals', [
                    'page'     => $page,
                    'per_page' => 200,
                ]);

                $records = $response['data'] ?? [];

                if (empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    try {
                        $contactId = $record['Contact_Name']['id'] ?? null;
                        $zohoClient = $contactId
                            ? ZohoClient::where('zoho_id', $contactId)->first()
                            : null;

                        $existing = ZohoDeal::where('zoho_id', $record['id'])->first();

                        $attributes = [
                            'zoho_id'        => $record['id'],
                            'zoho_client_id' => $zohoClient?->id,
                            'deal_name'      => $record['Deal_Name'] ?? null,
                            'amount'         => $record['Amount'] ?? null,
                            'stage'          => $record['Stage'] ?? null,
                            'pipeline'       => $record['Pipeline'] ?? null,
                            'closing_date'   => isset($record['Closing_Date']) ? Carbon::parse($record['Closing_Date']) : null,
                            'deal_owner'     => $record['Owner']['name'] ?? ($record['Owner'] ?? null),
                            'raw_data'       => $record,
                            'last_synced_at' => Carbon::now(),
                        ];

                        if ($existing) {
                            $existing->update($attributes);
                            $updated++;
                        } else {
                            ZohoDeal::create($attributes);
                            $created++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Zoho deal sync: failed to process record', [
                            'zoho_id' => $record['id'] ?? 'unknown',
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

                $hasMore = ($response['info']['more_records'] ?? false) === true;
                $page++;
            }

            $log->update([
                'status'          => 'completed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'completed_at'    => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Zoho deals sync failed', ['error' => $e->getMessage()]);

            $log->update([
                'status'          => 'failed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'error_message'   => $e->getMessage(),
                'completed_at'    => Carbon::now(),
            ]);
        }

        return $log;
    }

    /**
     * Sync activities from Zoho CRM into the zoho_activities table.
     */
    public function syncActivities(string $triggeredBy = 'system'): ZohoSyncLog
    {
        $log = ZohoSyncLog::create([
            'sync_type'    => 'activities',
            'status'       => 'running',
            'started_at'   => Carbon::now(),
            'triggered_by' => $triggeredBy,
        ]);

        $created = 0;
        $updated = 0;
        $failed  = 0;

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->zohoService->apiGet('Activities', [
                    'page'     => $page,
                    'per_page' => 200,
                ]);

                $records = $response['data'] ?? [];

                if (empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    try {
                        $contactId = $record['Who_Id']['id'] ?? null;
                        $zohoClient = $contactId
                            ? ZohoClient::where('zoho_id', $contactId)->first()
                            : null;

                        $existing = ZohoActivity::where('zoho_id', $record['id'])->first();

                        $attributes = [
                            'zoho_id'        => $record['id'],
                            'zoho_client_id' => $zohoClient?->id,
                            'activity_type'  => $record['Activity_Type'] ?? ($record['Type'] ?? null),
                            'subject'        => $record['Subject'] ?? null,
                            'description'    => $record['Description'] ?? null,
                            'activity_date'  => isset($record['Activity_Date']) ? Carbon::parse($record['Activity_Date']) : null,
                            'status'         => $record['Status'] ?? null,
                            'raw_data'       => $record,
                            'last_synced_at' => Carbon::now(),
                        ];

                        if ($existing) {
                            $existing->update($attributes);
                            $updated++;
                        } else {
                            ZohoActivity::create($attributes);
                            $created++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Zoho activity sync: failed to process record', [
                            'zoho_id' => $record['id'] ?? 'unknown',
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

                $hasMore = ($response['info']['more_records'] ?? false) === true;
                $page++;
            }

            $log->update([
                'status'          => 'completed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'completed_at'    => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Zoho activities sync failed', ['error' => $e->getMessage()]);

            $log->update([
                'status'          => 'failed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'error_message'   => $e->getMessage(),
                'completed_at'    => Carbon::now(),
            ]);
        }

        return $log;
    }

    /**
     * Sync notes from Zoho CRM into the zoho_notes table.
     */
    public function syncNotes(string $triggeredBy = 'system'): ZohoSyncLog
    {
        $log = ZohoSyncLog::create([
            'sync_type'    => 'notes',
            'status'       => 'running',
            'started_at'   => Carbon::now(),
            'triggered_by' => $triggeredBy,
        ]);

        $created = 0;
        $updated = 0;
        $failed  = 0;

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->zohoService->apiGet('Notes', [
                    'page'     => $page,
                    'per_page' => 200,
                ]);

                $records = $response['data'] ?? [];

                if (empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    try {
                        $parentId = $record['Parent_Id']['id'] ?? null;
                        $zohoClient = $parentId
                            ? ZohoClient::where('zoho_id', $parentId)->first()
                            : null;

                        $existing = ZohoNote::where('zoho_id', $record['id'])->first();

                        $attributes = [
                            'zoho_id'         => $record['id'],
                            'zoho_client_id'  => $zohoClient?->id,
                            'note_content'    => $record['Note_Content'] ?? null,
                            'note_title'      => $record['Note_Title'] ?? null,
                            'created_by_name' => $record['Created_By']['name'] ?? ($record['Created_By'] ?? null),
                            'raw_data'        => $record,
                            'last_synced_at'  => Carbon::now(),
                        ];

                        if ($existing) {
                            $existing->update($attributes);
                            $updated++;
                        } else {
                            ZohoNote::create($attributes);
                            $created++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Zoho note sync: failed to process record', [
                            'zoho_id' => $record['id'] ?? 'unknown',
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

                $hasMore = ($response['info']['more_records'] ?? false) === true;
                $page++;
            }

            $log->update([
                'status'          => 'completed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'completed_at'    => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Zoho notes sync failed', ['error' => $e->getMessage()]);

            $log->update([
                'status'          => 'failed',
                'records_synced'  => $created + $updated,
                'records_created' => $created,
                'records_updated' => $updated,
                'records_failed'  => $failed,
                'error_message'   => $e->getMessage(),
                'completed_at'    => Carbon::now(),
            ]);
        }

        return $log;
    }
}
