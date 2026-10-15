<?php

namespace App\Services\Audit;

use App\Enums\ValidationStatus;
use App\Models\AccessLogEntry;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * The gate access log: the page's props and its CSV rows, both from
 * filtered(), so the table and the export cannot drift apart.
 */
class AccessLogPresenter
{
    public const EXPORT_HEADER = ['Timestamp', 'Name', 'Role', 'Method', 'Gate', 'Pass ID', 'Result', 'Deny Reason'];

    /**
     * @param  array{gate?: ?string, result?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function props(User $viewer, array $filters): array
    {
        $query = $this->filtered($viewer, $filters);

        return [
            'entries' => $query->clone()
                ->latest('occurred_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (AccessLogEntry $e) => [
                    'id' => $e->id,
                    'userName' => $e->user_name,
                    'userRole' => $e->user_role,
                    'method' => $e->method,
                    'gate' => $e->gate,
                    'passId' => $e->pass_id,
                    'result' => $e->result,
                    'denyReason' => $e->deny_reason,
                    'timestamp' => $e->occurred_at->toIso8601String(),
                ]),

            /*
             | The headline of an access log is how many attempts were refused,
             | which is the one number the original page could not show.
             | Counted over the active filter, not the whole table.
             */
            'stats' => [
                'total' => $query->clone()->count(),
                'denied' => $query->clone()->where('result', ValidationStatus::Deny->value)->count(),
            ],

            'filters' => $filters,

            /*
             | Gate names are free text on this table — scans store the label
             | from config('gatepass.gates'), the visitor API accepts whatever it
             | is given, and the seeder writes a "Pedestrian Gate" that is in
             | neither. Reading the distinct values back is the only option that
             | matches what is actually in the log.
             */
            'gates' => AccessLogEntry::query()->distinct()->orderBy('gate')->pluck('gate')->all(),

            // ALLOW/DENY are scan outcomes; CHECK_IN/CHECK_OUT are confirmed movements.
            'results' => [...array_column(ValidationStatus::cases(), 'value'), 'CHECK_IN', 'CHECK_OUT'],

            'canExport' => $viewer->can('manageSecurity'),
        ];
    }

    /**
     * The export rows, read lazily by id. lazyById, not an offset cursor:
     * this table is append-heavy, and rows arriving mid-export would shift
     * every later offset, silently duplicating and skipping records. The cost
     * is that the file comes out oldest-first.
     *
     * @param  array{gate?: ?string, result?: ?string, from?: ?string, to?: ?string}  $filters
     * @return LazyCollection<int, list<mixed>>
     */
    public function exportRows(User $viewer, array $filters): LazyCollection
    {
        return $this->filtered($viewer, $filters)->lazyById(500)->map(fn (AccessLogEntry $e) => [
            $e->occurred_at->toDateTimeString(),
            $e->user_name,
            $e->user_role,
            $e->method,
            $e->gate,
            $e->pass_id,
            $e->result,
            $e->deny_reason,
        ]);
    }

    /**
     * @param  array{gate?: ?string, result?: ?string, from?: ?string, to?: ?string}  $filters
     */
    private function filtered(User $viewer, array $filters): Builder
    {
        return AccessLogEntry::query()
            /*
             | Defence in depth. The route carries `can:manageSecurity`, so this
             | branch is unreachable today; it means that relaxing the gate shows
             | a resident their own gate activity rather than the whole estate's.
             */
            ->when(! $viewer->can('manageSecurity'), fn ($q) => $q->where('user_id', $viewer->id))
            ->when(filled($filters['gate'] ?? null), fn ($q) => $q->where('gate', $filters['gate']))
            ->when(filled($filters['result'] ?? null), fn ($q) => $q->where('result', $filters['result']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('occurred_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('occurred_at', '<=', Carbon::parse($filters['to'])->endOfDay()));
    }
}
