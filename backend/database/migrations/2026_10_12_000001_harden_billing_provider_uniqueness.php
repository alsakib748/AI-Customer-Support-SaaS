<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harden provider billing identifiers and webhook bookkeeping.
     *
     * Duplicate provider ids cause duplicate billing, so we enforce uniqueness
     * at the database level. Existing duplicates cannot be reconciled safely,
     * so the index creation is skipped (and logged) when data conflicts exist.
     */
    public function up(): void
    {
        Schema::connection('central')->table('payment_webhook_events', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('payment_webhook_events', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0)->after('status');
            }
            if (! Schema::connection('central')->hasColumn('payment_webhook_events', 'last_error')) {
                $table->text('last_error')->nullable()->after('error_message');
            }
            if (! Schema::connection('central')->hasColumn('payment_webhook_events', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('processed_at');
            }
        });

        $this->addUniqueIfPossible('payments', ['provider', 'provider_payment_id'], 'payments_provider_payment_unique');
        $this->addUniqueIfPossible('payments', ['provider', 'provider_invoice_id'], 'payments_provider_invoice_unique');
        $this->addUniqueIfPossible('invoices', ['stripe_invoice_id'], 'invoices_stripe_invoice_unique');
    }

    public function down(): void
    {
        $this->dropUniqueIfExists('invoices', 'invoices_stripe_invoice_unique');
        $this->dropUniqueIfExists('payments', 'payments_provider_invoice_unique');
        $this->dropUniqueIfExists('payments', 'payments_provider_payment_unique');

        Schema::connection('central')->table('payment_webhook_events', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_error', 'received_at']);
        });
    }

    protected function addUniqueIfPossible(string $table, array $columns, string $indexName): void
    {
        $connection = Schema::connection('central');

        if (! $connection->hasTable($table)) {
            return;
        }

        $duplicates = \DB::connection('central')
            ->table($table)
            ->select($columns)
            ->whereNotNull($columns[count($columns) - 1])
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicates) {
            \Log::warning("Skipping unique index {$indexName}: existing duplicate rows", [
                'table'   => $table,
                'columns' => $columns,
            ]);
            return;
        }

        try {
            $connection->table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->unique($columns, $indexName);
            });
        } catch (\Throwable $e) {
            \Log::warning("Unable to add unique index {$indexName}", ['error' => $e->getMessage()]);
        }
    }

    protected function dropUniqueIfExists(string $table, string $indexName): void
    {
        try {
            Schema::connection('central')->table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropUnique($indexName);
            });
        } catch (\Throwable $e) {
            // Index already absent.
        }
    }
};
