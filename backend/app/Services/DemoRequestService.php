<?php

namespace App\Services;

use App\Mail\DemoRequestNotification;
use App\Mail\DemoRequestReceived;
use App\Models\DemoRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class DemoRequestService
{
    public function store(array $data): DemoRequest
    {
        $demoRequest = DemoRequest::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'team_size' => $data['team_size'] ?? null,
            'interests' => $data['interests'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);
        try {
            Mail::to($demoRequest->email)->send(new DemoRequestReceived($demoRequest));
        } catch (\Exception $e) {
            Log::warning('Failed to send demo request confirmation email');
        }
        try {
            Mail::to(config('mail.from.address', config('mail.support_email', 'support@example.local')))->send(new DemoRequestNotification($demoRequest));
        } catch (\Exception $e) {
            Log::warning('Failed to send demo request notification email');
        }

        return $demoRequest;
    }

    public function getAll($perPage = 20)
    {
        return DemoRequest::latest()->paginate($perPage);
    }

    public function find($id): DemoRequest
    {
        return DemoRequest::findOrFail($id);
    }

    public function updateStatus($id, array $data): DemoRequest
    {
        $demoRequest = $this->find($id);
        $allowed = ['pending', 'scheduled', 'completed', 'cancelled', 'contacted'];
        if (! in_array($data['status'], $allowed)) {
            throw ValidationException::withMessages(['status' => 'Invalid status.']);
        }
        $demoRequest->update([
            'status' => $data['status'],
            'scheduled_at' => $data['scheduled_at'] ?? $demoRequest->scheduled_at,
            'meeting_link' => $data['meeting_link'] ?? $demoRequest->meeting_link,
            'notes' => $data['notes'] ?? $demoRequest->notes,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return $demoRequest;
    }
}
