<?php

namespace App\Console\Commands\Ops;

use App\Models\PlanProviderPrice;
use App\Support\Ops\ProductionConfigValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ProductionCheckCommand extends Command
{
    protected $signature = 'app:production-check
        {--allow-non-production : Validate a production-like configuration even when APP_ENV is not "production"}';

    protected $description = 'Validate that the application is safely configured for a production deployment';

    public function handle(ProductionConfigValidator $validator): int
    {
        $issues = $validator->validate(
            $this->snapshot(),
            (bool) $this->option('allow-non-production')
        );

        foreach ($this->planPriceIssues() as $message) {
            $issues[] = ['level' => 'error', 'key' => 'PLAN_PRICE_MAPPING', 'message' => $message];
        }

        if (empty($issues)) {
            $this->info('Production configuration check passed.');

            return self::SUCCESS;
        }

        $this->table(
            ['Level', 'Key', 'Issue'],
            array_map(
                fn (array $issue) => [strtoupper($issue['level']), $issue['key'], $issue['message']],
                $issues
            )
        );

        $errors = count(array_filter($issues, fn (array $issue) => $issue['level'] === 'error'));
        $warnings = count($issues) - $errors;

        if ($errors > 0) {
            $this->error("Production configuration check failed: {$errors} error(s), {$warnings} warning(s).");

            return self::FAILURE;
        }

        $this->warn("Production configuration check passed with {$warnings} warning(s).");

        return self::SUCCESS;
    }

    /**
     * Build a resolved configuration snapshot from the current config() state.
     *
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'environment' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'key' => config('app.key'),
            'jwt_secret' => config('jwt.secret'),
            'app_url' => (string) config('app.url'),
            'database_default' => config('database.default'),
            'cache_default' => config('cache.default'),
            'filesystems_default' => config('filesystems.default'),
            'mail_default' => config('mail.default'),
            'mail_from' => config('mail.from.address'),
            'cors_origins' => (array) config('cors.allowed_origins'),
            'session_secure' => (bool) config('session.secure'),
            'session_encrypt' => (bool) config('session.encrypt'),
            'stripe' => array_merge(
                (array) config('billing.providers.stripe', []),
                (array) config('payment.providers.stripe', [])
            ),
            'paypal' => array_merge(
                (array) config('billing.providers.paypal', []),
                (array) config('payment.providers.paypal', [])
            ),
            'ai_enabled' => (bool) config('ai.features.enabled'),
            'ai_default_provider' => config('ai.default'),
            'ai_embeddings_enabled' => (bool) config('ai.features.embedding'),
            'ai_embedding_provider' => config('ai.embeddings.provider'),
            'ai_providers' => config('ai.providers', []),
        ];
    }

    /**
     * Detect placeholder / duplicate provider price mappings for purchasable plans.
     *
     * @return array<int, string>
     */
    private function planPriceIssues(): array
    {
        try {
            if (! Schema::connection('central')->hasTable('plan_provider_prices')) {
                return [];
            }

            $rows = PlanProviderPrice::query()->get();
        } catch (\Throwable $e) {
            // Database not reachable during pre-deploy checks; config rules still apply.
            return [];
        }

        $issues = [];

        foreach ($rows as $row) {
            $priceId = (string) $row->provider_price_id;

            if ($priceId === '' || stripos($priceId, 'replace') !== false || stripos($priceId, 'your-') !== false) {
                $issues[] = sprintf(
                    'Plan %s (%s/%s) has a placeholder or empty provider price id.',
                    $row->plan_id,
                    $row->provider,
                    $row->billing_cycle
                );
            }
        }

        $duplicates = $rows
            ->groupBy(fn (PlanProviderPrice $row) => implode('|', [$row->provider, $row->billing_cycle, $row->provider_price_id]))
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($duplicates as $group) {
            $first = $group->first();

            $issues[] = sprintf(
                'Duplicate %s/%s price id "%s" is mapped to multiple plans (%s).',
                $first->provider,
                $first->billing_cycle,
                $first->provider_price_id,
                $group->pluck('plan_id')->unique()->implode(', ')
            );
        }

        return array_values(array_unique($issues));
    }
}
