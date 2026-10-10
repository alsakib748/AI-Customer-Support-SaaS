<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Health\Services\HealthCheckRegistry;
use App\Health\Services\HealthCheckRunner;
use App\Health\Services\HealthCheckStorage;
use App\Health\Services\HealthSnapshotService;
use App\Health\Services\IncidentDetectionService;
use App\Health\Services\MetricsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemHealth\RunHealthCheckRequest;
use App\Http\Resources\Admin\HealthCheckResultResource;
use App\Http\Resources\Admin\HealthSnapshotResource;
use App\Http\Resources\Admin\PlatformIncidentResource;
use App\Models\PlatformIncident;
use App\Models\PlatformIncidentEvent;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Platform-only System Health API. All reads are served from stored snapshots;
 * live probes happen only via the rate-limited on-demand run endpoint.
 */
class SystemHealthController extends Controller
{
    public function __construct(
        protected HealthCheckRegistry $registry,
        protected HealthCheckRunner $runner,
        protected HealthCheckStorage $storage,
        protected HealthSnapshotService $snapshot,
        protected IncidentDetectionService $incidents,
        protected MetricsService $metrics,
        protected AuditLogService $audit,
    ) {
    }

    /**
     * Dashboard snapshot (never executes a live probe).
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PlatformIncident::class);

        $fresh = filter_var($request->query('fresh', false), FILTER_VALIDATE_BOOL);

        return response()->json([
            'success' => true,
            'data'    => HealthSnapshotResource::make($this->snapshot->get($fresh))->resolve(),
        ]);
    }

    /**
     * Individual check statuses from the current snapshot.
     */
    public function checks()
    {
        Gate::authorize('viewAny', PlatformIncident::class);

        $snapshot = $this->snapshot->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'overall' => $snapshot['overall'],
                'counts'  => $snapshot['counts'],
                'checks'  => $snapshot['checks'],
            ],
        ]);
    }

    /**
     * Latest outcome + recent history for a single check.
     */
    public function showCheck(string $check)
    {
        Gate::authorize('viewAny', PlatformIncident::class);

        if (! $this->registry->has($check)) {
            return response()->json(['success' => false, 'message' => 'Unknown health check.'], 404);
        }

        $latest = $this->storage->latestFor($check);

        return response()->json([
            'success' => true,
            'data'    => [
                'latest'  => $latest ? [
                    'check_key'   => $latest->check_key,
                    'status'      => $latest->status,
                    'summary'     => $latest->summary,
                    'metadata'    => $latest->metadata ?? [],
                    'duration_ms' => (int) $latest->duration_ms,
                    'checked_at'  => $latest->checked_at?->toIso8601String(),
                ] : null,
                'history' => HealthCheckResultResource::collection($this->storage->history($check, 60))->resolve(),
            ],
        ]);
    }

    /**
     * Manual, rate-limited on-demand run of one check.
     */
    public function runCheck(RunHealthCheckRequest $request, string $check)
    {
        $result = $this->runner->runOne($check);

        $this->incidents->evaluate([$result], 'manual', Auth::id());

        $this->audit->log(
            'health.check_run',
            'system_health',
            $check,
            null,
            [
                'status'      => $result->status->value,
                'duration_ms' => $result->durationMs,
            ],
            ['check_key' => $check],
        );

        return response()->json([
            'success' => true,
            'data'    => $result->toArray(),
        ]);
    }

    /**
     * Metric samples + summary stats for a metric key.
     */
    public function metrics(Request $request)
    {
        Gate::authorize('viewAny', PlatformIncident::class);

        $metric = (string) $request->query('metric', 'check.duration_ms');

        $from = $request->query('from') ? Carbon::parse((string) $request->query('from')) : null;
        $to = $request->query('to') ? Carbon::parse((string) $request->query('to')) : null;
        $limit = min(max((int) $request->query('limit', 500), 1), 1000);

        $source = $request->has('source') ? (string) $request->query('source') : null;

        $query = $this->metrics->query(
            $metric,
            $source,
            $from,
            $to,
            $limit,
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'metric'        => $query,
                'availability'  => $this->metrics->availability($source, $from, $to),
                'metrics_known' => $this->metrics->metricKeys(),
            ],
        ]);
    }

    /**
     * Filterable incident list.
     */
    public function incidents(Request $request)
    {
        Gate::authorize('viewAny', PlatformIncident::class);

        $query = PlatformIncident::query()
            ->with('acknowledgedBy', 'resolvedBy')
            ->orderByDesc('started_at');

        if ($request->has('status')) {
            match ($request->query('status')) {
                'active'                  => $query->active(),
                'open'                    => $query->where('status', 'open'),
                'acknowledged'            => $query->where('status', 'acknowledged'),
                'resolved'                => $query->where('status', 'resolved'),
                default                   => $query->where('status', (string) $request->query('status')),
            };
        }

        if ($request->has('severity')) {
            $query->where('severity', (string) $request->query('severity'));
        }

        if ($request->has('source')) {
            $query->where('source', (string) $request->query('source'));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return PlatformIncidentResource::collection($query->paginate($perPage))->response();
    }

    /**
     * Incident detail + timeline.
     */
    public function showIncident(PlatformIncident $incident)
    {
        Gate::authorize('view', $incident);

        $incident->load(['events' => fn ($q) => $q->orderByDesc('id')->with('actor'), 'acknowledgedBy', 'resolvedBy']);

        return response()->json([
            'success' => true,
            'data'    => PlatformIncidentResource::make($incident)->resolve(),
        ]);
    }

    public function acknowledgeIncident(Request $request, PlatformIncident $incident)
    {
        Gate::authorize('acknowledge', $incident);

        $updated = $this->incidents->acknowledge($incident, Auth::id());

        $this->audit->log(
            'health.incident_acknowledged',
            'platform_incident',
            $incident->id,
            ['status' => 'open'],
            ['status' => 'acknowledged'],
            ['title' => $incident->title, 'fingerprint' => $incident->fingerprint],
        );

        return response()->json([
            'success' => true,
            'data'    => PlatformIncidentResource::make($updated->refresh()->load('events', 'acknowledgedBy', 'resolvedBy'))->resolve(),
        ]);
    }

    public function resolveIncident(Request $request, PlatformIncident $incident)
    {
        Gate::authorize('resolve', $incident);

        $oldStatus = $incident->status->value;
        $title = $incident->title;
        $fingerprint = $incident->fingerprint;

        $updated = $this->incidents->resolve($incident, Auth::id());

        $this->audit->log(
            'health.incident_resolved',
            'platform_incident',
            $incident->id,
            ['status' => $oldStatus],
            ['status' => 'resolved'],
            ['title' => $title, 'fingerprint' => $fingerprint],
        );

        return response()->json([
            'success' => true,
            'data'    => PlatformIncidentResource::make($updated->refresh()->load('events', 'acknowledgedBy', 'resolvedBy'))->resolve(),
        ]);
    }

    /**
     * Recent operational events (failures, recoveries, acknowledgements).
     */
    public function events(Request $request)
    {
        Gate::authorize('viewAny', PlatformIncident::class);

        $limit = min(max((int) $request->query('limit', 30), 1), 100);

        $events = PlatformIncidentEvent::query()
            ->with(['incident'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $events->map(fn (PlatformIncidentEvent $event) => [
                'id'           => $event->id,
                'event'        => $event->event,
                'severity'     => $event->severity?->value,
                'summary'      => $event->summary,
                'incident_id'  => $event->incident_id,
                'incident_title' => $event->incident?->title,
                'incident_status' => $event->incident?->status?->value,
                'created_at'   => $event->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}