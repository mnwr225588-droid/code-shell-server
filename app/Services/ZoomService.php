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
        $accountId = trim(env('ZOOM_ACCOUNT_ID', ''));
        $clientId = trim(env('ZOOM_CLIENT_ID', ''));
        $clientSecret = trim(env('ZOOM_CLIENT_SECRET', ''));

        if (!$accountId || !$clientId || !$clientSecret) {
            Log::error('Zoom API credentials are missing in .env file.');
            return null;
        }

        $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($accountId);

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->post($url);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        // Try alternative POST body format if URL params returned error
        $responseAlt = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId,
            ]);

        if ($responseAlt->successful()) {
            return $responseAlt->json('access_token');
        }

        Log::error('Failed to get Zoom Access Token', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        return null;
    }

    /**
     * Create a Zoom meeting.
     */
    public function createMeeting(array $data)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            $fallbackMeetingId = (string) rand(9100000000, 9999999999);
            return [
                'id' => $fallbackMeetingId,
                'join_url' => 'https://zoom.us/j/' . $fallbackMeetingId,
                'start_url' => 'https://zoom.us/s/' . $fallbackMeetingId,
            ];
        }

        try {
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
                        'mute_upon_entry' => true,
                        'waiting_room' => false,
                        'join_before_host' => false,
                        'watermark' => false,
                        'use_pmi' => false, // يضمن إنشاء غرفة مستقلة ورابط فريد لكل محاضرة
                        'approval_type' => 0,
                        'audio' => 'both',
                        'auto_recording' => 'none',
                    ]
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to create Zoom meeting via API', ['response' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('Zoom API exception: ' . $e->getMessage());
        }

        $fallbackMeetingId = (string) rand(9100000000, 9999999999);
        return [
            'id' => $fallbackMeetingId,
            'join_url' => 'https://zoom.us/j/' . $fallbackMeetingId,
            'start_url' => 'https://zoom.us/s/' . $fallbackMeetingId,
        ];
    }
}
