<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;

class AnalyticsCacheObserver
{

    protected array $metrics = [
        'overview',
        'conversations',
        'customers',
        'agents',
        'tickets',
        'ai',
        'widget',
        'knowledge_base',
    ];

    /**
     * Handle the Analytics "created" event.
     */
    public function created($model): void
    {
        $this->flush();
    }

    /**
     * Handle the Analytics "updated" event.
     */
    public function updated($model): void
    {
        $this->flush();
    }

    /**
     * Handle the Analytics "deleted" event.
     */
    public function deleted($model): void
    {
        $this->flush();
    }

    protected function flush(): void
    {
        $tenantId = tenant()?->id;
        if (!$tenantId)
            return;

        foreach ($this->metrics as $metric) {
            Cache::flush("analytics:{$tenantId}:{$metric}:*");
        }
    }
}
