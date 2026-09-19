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
            'customers' => $this->formatUsage('customers'),
            'widgets' => $this->formatUsage('widgets'),
            'documents' => $this->formatUsage('documents'),
            'kb_articles' => $this->formatUsage('kb_articles'),
            'conversations' => $this->formatUsage('conversations'),
            'storage' => $this->formatStorageUsage('storage'),
        ];
    }

    protected function formatUsage(string $type): array
    {
        $data = $this[$type] ?? [];
        $limit = (int) ($data['limit'] ?? 0);
        $used = (int) ($data['used'] ?? 0);
        $pct = (float) ($data['percentage'] ?? 0);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $data['remaining'] ?? max(0, $limit - $used),
            'percentage' => $pct,
            'is_unlimited' => $limit <= 0,
            'is_near_limit' => $limit > 0 && $pct >= 80,
            'is_at_limit' => $limit > 0 && $pct >= 100,
        ];
    }

    protected function formatStorageUsage(string $type): array
    {
        $data = $this[$type] ?? [];
        $limit = (int) ($data['limit'] ?? 0);
        $used = (int) ($data['used'] ?? 0);
        $remaining = (int) ($data['remaining'] ?? max(0, $limit - $used));

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => (float) ($data['percentage'] ?? 0),
            'is_unlimited' => $limit <= 0,
            'formatted_used' => $this->formatBytes($used),
            'formatted_limit' => $limit > 0 ? $this->formatBytes($limit) : 'Unlimited',
            'formatted_remaining' => $limit > 0 ? $this->formatBytes($remaining) : 'Unlimited',
        ];
    }


    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0)
            return '0 B';

        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, $k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
