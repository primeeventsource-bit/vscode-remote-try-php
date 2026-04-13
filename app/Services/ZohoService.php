<?php

namespace App\Services;

use App\Models\ZohoToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoService
{
    protected string $baseUrl = 'https://www.zohoapis.com/crm/v2/';
    protected string $authUrl = 'https://accounts.zoho.com/oauth/v2/';

    /**
     * Build the Zoho OAuth authorization URL.
     */
    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'scope'         => 'ZohoCRM.modules.ALL,ZohoCRM.settings.ALL',
            'client_id'     => config('services.zoho.client_id'),
            'response_type' => 'code',
            'access_type'   => 'offline',
            'redirect_uri'  => config('services.zoho.redirect_uri'),
            'prompt'        => 'consent',
        ]);

        return $this->authUrl . 'auth?' . $params;
    }

    /**
     * Exchange the authorization code for access and refresh tokens.
     */
    public function handleCallback(string $code): ZohoToken
    {
        try {
            $response = Http::asForm()->post($this->authUrl . 'token', [
                'grant_type'    => 'authorization_code',
                'client_id'     => config('services.zoho.client_id'),
                'client_secret' => config('services.zoho.client_secret'),
                'redirect_uri'  => config('services.zoho.redirect_uri'),
                'code'          => $code,
            ]);

            $data = $response->json();

            if (!$response->successful() || isset($data['error'])) {
                throw new \RuntimeException('Zoho OAuth callback failed: ' . ($data['error'] ?? 'Unknown error'));
            }

            return ZohoToken::create([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at'    => Carbon::now()->addSeconds($data['expires_in'] ?? 3600),
                'grant_type'    => 'authorization_code',
            ]);
        } catch (\Exception $e) {
            Log::error('Zoho OAuth callback error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Refresh the access token using the stored refresh token.
     */
    public function refreshToken(): ZohoToken
    {
        try {
            $token = ZohoToken::getLatest();

            if (!$token || !$token->refresh_token) {
                throw new \RuntimeException('No Zoho refresh token available. Please re-authorize.');
            }

            $response = Http::asForm()->post($this->authUrl . 'token', [
                'grant_type'    => 'refresh_token',
                'client_id'     => config('services.zoho.client_id'),
                'client_secret' => config('services.zoho.client_secret'),
                'refresh_token' => $token->refresh_token,
            ]);

            $data = $response->json();

            if (!$response->successful() || isset($data['error'])) {
                throw new \RuntimeException('Zoho token refresh failed: ' . ($data['error'] ?? 'Unknown error'));
            }

            $token->update([
                'access_token' => $data['access_token'],
                'expires_at'   => Carbon::now()->addSeconds($data['expires_in'] ?? 3600),
                'grant_type'   => 'refresh_token',
            ]);

            return $token->fresh();
        } catch (\Exception $e) {
            Log::error('Zoho token refresh error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get a valid access token, refreshing if necessary.
     */
    protected function getValidToken(): string
    {
        if (!ZohoToken::isValid()) {
            $this->refreshToken();
        }

        $token = ZohoToken::getLatest();

        if (!$token) {
            throw new \RuntimeException('No Zoho token available. Please connect to Zoho first.');
        }

        return $token->access_token;
    }

    /**
     * Make an authenticated GET request to the Zoho CRM API.
     */
    public function apiGet(string $endpoint, array $params = []): array
    {
        try {
            $accessToken = $this->getValidToken();

            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            ])->get($this->baseUrl . ltrim($endpoint, '/'), $params);

            if ($response->status() === 401) {
                $this->refreshToken();
                $accessToken = ZohoToken::getLatest()->access_token;

                $response = Http::withHeaders([
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ])->get($this->baseUrl . ltrim($endpoint, '/'), $params);
            }

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "Zoho API GET {$endpoint} failed with status {$response->status()}: " . $response->body()
                );
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Zoho API GET error', [
                'endpoint' => $endpoint,
                'message'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Make an authenticated POST request to the Zoho CRM API.
     */
    public function apiPost(string $endpoint, array $data): array
    {
        try {
            $accessToken = $this->getValidToken();

            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            ])->post($this->baseUrl . ltrim($endpoint, '/'), $data);

            if ($response->status() === 401) {
                $this->refreshToken();
                $accessToken = ZohoToken::getLatest()->access_token;

                $response = Http::withHeaders([
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ])->post($this->baseUrl . ltrim($endpoint, '/'), $data);
            }

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "Zoho API POST {$endpoint} failed with status {$response->status()}: " . $response->body()
                );
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Zoho API POST error', [
                'endpoint' => $endpoint,
                'message'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
