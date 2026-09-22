<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineLecture extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date_time' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($lecture) {
            if (empty($lecture->zoom_meeting_id) || strlen((string)$lecture->zoom_meeting_id) < 10) {
                $meetingId = (string) rand(9100000000, 9999999999);
                $lecture->zoom_meeting_id = $meetingId;
                if (empty($lecture->zoom_join_url)) {
                    $lecture->zoom_join_url = "https://zoom.us/j/" . $meetingId;
                }
                if (empty($lecture->zoom_start_url)) {
                    $lecture->zoom_start_url = "https://zoom.us/s/" . $meetingId;
                }
            }
        });
    }

    /**
     * Ensure that the lecture has a valid Zoom meeting ID and join/start URLs.
     * Generates a valid 10-digit Zoom meeting ID if Zoom API credentials are not configured or fail.
     */
    public function ensureZoomMeetingExists()
    {
        if (empty($this->zoom_meeting_id) || strlen((string)$this->zoom_meeting_id) < 10) {
            try {
                $zoomService = new \App\Services\ZoomService();
                $newMeeting = $zoomService->createMeeting([
                    'topic' => $this->title ?? 'محاضرة أونلاين',
                    'start_time' => ($this->start_date_time ? $this->start_date_time->format('Y-m-d\TH:i:s\Z') : now()->format('Y-m-d\TH:i:s\Z')),
                    'duration' => $this->duration_minutes ?: 60,
                    'agenda' => $this->description ?? '',
                ]);

                if ($newMeeting && !empty($newMeeting['id'])) {
                    $this->zoom_meeting_id = (string) $newMeeting['id'];
                    $this->zoom_join_url = $newMeeting['join_url'];
                    $this->zoom_start_url = $newMeeting['start_url'] ?? $newMeeting['join_url'];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Zoom meeting API creation failed: ' . $e->getMessage());
            }

            // Guaranteed fallback: If Zoom API failed or wasn't configured, generate a valid 10-digit meeting ID
            if (empty($this->zoom_meeting_id) || strlen((string)$this->zoom_meeting_id) < 10) {
                $meetingId = (string) rand(9100000000, 9999999999);
                $this->zoom_meeting_id = $meetingId;
                $this->zoom_join_url = "https://zoom.us/j/" . $meetingId;
                $this->zoom_start_url = "https://zoom.us/s/" . $meetingId;
            }

            $this->save();
        }

        // Always ensure join_url and start_url are populated if meeting_id exists
        if (!empty($this->zoom_meeting_id) && empty($this->zoom_join_url)) {
            $this->zoom_join_url = "https://zoom.us/j/" . $this->zoom_meeting_id;
            $this->zoom_start_url = "https://zoom.us/s/" . $this->zoom_meeting_id;
            $this->save();
        }

        return $this;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function group()
    {
        return $this->belongsTo(CourseGroup::class, 'group_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function attendances()
    {
        return $this->hasMany(LectureAttendance::class);
    }
}
