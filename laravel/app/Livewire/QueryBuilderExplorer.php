<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Query\ApiQueryService;
use App\Support\EntityAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.livewire')]
class QueryBuilderExplorer extends Component
{
    #[Url(as: 'entity')]
    public string $entity = 'gate_passes';

    #[Url(as: 'sort')]
    public string $sort = '-created_at';

    public string $filterKeyword = '';

    public string $filterStatus = '';

    public array $selectedIncludes = [];

    public int $perPage = 10;

    public int $page = 1;

    public string $viewMode = 'table'; // 'table' or 'json'

    /**
     * Opening on an entity this account may not browse falls back to one it
     * may, rather than to whatever `?entity=` in the URL asked for.
     */
    public function mount(): void
    {
        $visible = EntityAccess::visibleTo(Auth::user());

        abort_if($visible === [], 403);

        if (! in_array($this->entity, $visible, true)) {
            $this->setEntity($visible[0]);
        }
    }

    public function setEntity(string $entity): void
    {
        if (EntityAccess::allows(Auth::user(), $entity)) {
            $this->entity = $entity;
            $this->sort = match ($entity) {
                'users' => 'name',
                default => '-created_at',
            };
            $this->filterKeyword = '';
            $this->filterStatus = '';
            $this->selectedIncludes = [];
            $this->page = 1;
        }
    }

    public function toggleInclude(string $include): void
    {
        if (in_array($include, $this->selectedIncludes, true)) {
            $this->selectedIncludes = array_diff($this->selectedIncludes, [$include]);
        } else {
            $this->selectedIncludes[] = $include;
        }
    }

    public function buildQueryString(): string
    {
        $params = [];

        if (! empty($this->filterStatus)) {
            $params['filter']['status'] = $this->filterStatus;
        }

        if (! empty($this->filterKeyword)) {
            $keywordKey = match ($this->entity) {
                'gate_passes' => 'holder_name',
                'visitors' => 'name',
                'transactions' => 'purpose',
                'users' => 'name',
                'warnings' => 'title',
                default => 'name',
            };
            $params['filter'][$keywordKey] = $this->filterKeyword;
        }

        if (! empty($this->sort)) {
            $params['sort'] = $this->sort;
        }

        if (! empty($this->selectedIncludes)) {
            $params['include'] = implode(',', $this->selectedIncludes);
        }

        $params['page'] = $this->page;
        $params['per_page'] = $this->perPage;

        return '/api/v1/'.$this->entity.'?'.http_build_query($params);
    }

    public function render(ApiQueryService $queryService): View
    {
        abort_unless(EntityAccess::allows(Auth::user(), $this->entity), 403);

        $meta = array_intersect_key(
            $queryService->getEntitiesMeta(),
            array_flip(EntityAccess::visibleTo(Auth::user())),
        );
        $currentMeta = $meta[$this->entity] ?? [];

        // Build mock synthetic request for Spatie QueryBuilder
        $queryParameters = [];

        if (! empty($this->filterStatus)) {
            $queryParameters['filter']['status'] = $this->filterStatus;
        }

        if (! empty($this->filterKeyword)) {
            $keywordKey = match ($this->entity) {
                'gate_passes' => 'holder_name',
                'visitors' => 'name',
                'transactions' => 'purpose',
                'users' => 'name',
                'warnings' => 'title',
                default => 'name',
            };
            $queryParameters['filter'][$keywordKey] = $this->filterKeyword;
        }

        if (! empty($this->sort)) {
            $queryParameters['sort'] = $this->sort;
        }

        if (! empty($this->selectedIncludes)) {
            $queryParameters['include'] = implode(',', $this->selectedIncludes);
        }

        $request = Request::create('/api/v1/'.$this->entity, 'GET', $queryParameters);

        $results = $queryService
            ->forEntity($this->entity, $request)
            ->paginate($this->perPage);

        return view('livewire.query-builder-explorer', [
            'meta' => $meta,
            'currentMeta' => $currentMeta,
            'results' => $results,
            'queryStringPreview' => $this->buildQueryString(),
        ]);
    }
}
