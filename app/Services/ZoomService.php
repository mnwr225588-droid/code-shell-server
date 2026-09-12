<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomService
{
    /**
     * Get Server-to-Server OAuth access token.
     */
    private function getAccessToken()
    {
        $accountId = env('ZOOM_ACCOUNT_ID');
        $clientId = env('ZOOM_CLIENT_ID');
        $clientSecret = env('ZOOM_CLIENT_SECRET');

        if (!$accountId || !$clientId || !$clientSecret) {
            Log::error('Zoom API credentials are not set.');
            return null;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId,
            ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        Log::error('Failed to get Zoom Access Token', ['response' => $response->body()]);
        return null;
    }

    /**
     * Create a Zoom meeting.
     */
    public function createMeeting(array $data)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $response = Http::withToken($token)
            ->post('https://api.zoom.us/v2/users/me/meetings', [
                'topic' => $data['topic'] ?? 'Online Lecture',
                'type' => 2, // Scheduled meeting
                'start_time' => $data['start_time'], // Format: yyyy-MM-dd'T'HH:mm:ss'Z'
                'duration' => $data['duration'], // Duration in minutes
                'timezone' => 'UTC',
                'agenda' => $data['agenda'] ?? '',
                'settings' => [
                    'host_video' => true,
                    'participant_video' => false,
                    'join_before_host' => false,
                    'mute_upon_entry' => true,
                    'watermark' => false,
                    'use_pmi' => false,
                    'approval_type' => 0,
                    'audio' => 'both',
                    'auto_recording' => 'cloud',
                ]
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Failed to create Zoom meeting', ['response' => $response->body()]);
        return null;
    }
}
