<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ai' => $this->formatUsage('ai'),
            'agents' => $this->formatUsage('agents'),
            'documents' => $this->formatUsage('documents'),
            'storage' => $this->formatStorageUsage('storage'),
            'conversations' => $this->formatUsage('conversations'),
        ];
    }

    protected function formatUsage(string $type): array
    {
        $data = $this[$type] ?? [];
        $limit = $data['limit'] ?? 0;

        return [
            'used' => $data['used'] ?? 0,
            'limit' => $limit,
            'remaining' => $data['remaining'] ?? 0,
            'percentage' => $data['percentage'] ?? 0,
            'is_unlimited' => $limit <= 0,
            'is_near_limit' => $limit > 0 && ($data['percentage'] ?? 0) >= 80,
            'is_at_limit' => $limit > 0 && ($data['percentage'] ?? 0) >= 100,
        ];
    }

    protected function formatStorageUsage(string $type): array
    {
        $data = $this[$type] ?? [];
        $limit = $data['limit'] ?? 0;
        $used = $data['used'] ?? 0;

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $data['remaining'] ?? 0,
            'percentage' => $data['percentage'] ?? 0,
            'is_unlimited' => $limit <= 0,
            'formatted_used' => $this->formatBytes($used),
            'formatted_limit' => $limit > 0 ? $this->formatBytes($limit) : 'Unlimited',
            'formatted_remaining' => $limit > 0
                ? $this->formatBytes($data['remaining'] ?? 0)
                : 'Unlimited',
        ];
    }


    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0)
            return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}