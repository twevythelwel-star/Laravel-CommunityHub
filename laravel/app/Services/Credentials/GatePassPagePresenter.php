<?php

namespace App\Services\Credentials;

use App\Enums\GateId;
use App\Models\AccessLogEntry;
use App\Models\User;
use App\Repositories\GatePassRepository;
use App\Services\GatePassEngine;

/**
 * The props of the two gate pass pages: the holder's own pass
 * (Dashboard/GatePass, issued on first visit) and the guard's scanner
 * (Dashboard/GateScanner).
 */
class GatePassPagePresenter
{
    public function __construct(
        private readonly GatePassEngine $engine,
        private readonly GatePassRepository $repository,
    ) {}

    /**
     * The user's pass and its look; security also get the pass directory and
     * the staff list.
     *
     * @return array<string, mixed>
     */
    public function pass(User $user): array
    {
        $pass = $this->engine->issuePassFor($user);
        $category = $pass->category;
        $canManage = $user->can('manageSecurity');

        return [
            'pass' => [
                'id' => $pass->id,
                'passId' => $pass->pass_id,
                'category' => $category->value,
                'holderName' => $pass->holder_name,
                'property' => $pass->property,
                'accessZone' => $pass->access_zone,
                'gate' => $pass->designated_gate->value,
                'rotationSeq' => $pass->rotation_seq,
                'status' => $pass->status->value,
            ],
            'visual' => [
                'variant' => $this->engine->variantFor($pass),
                'shape' => $category->shape()->value,
            ],
            'policy' => $this->repository->camelPolicy($this->engine->policyFor($category)),
            'categoryConfigs' => $this->repository->getCategoryConfigs(),
            'directory' => $canManage ? $this->repository->getDirectory() : [],
            'staff' => $canManage ? $this->repository->getStaffList() : [],
            'windowSeconds' => (int) config('gatepass.window_seconds'),
            'gates' => config('gatepass.gates'),
            'can' => [
                'scan' => $user->can('scanPasses'),
                'manageSecurity' => $canManage,
                'manageUsers' => $user->can('manageUsers'),
            ],
        ];
    }

    /**
     * The gates to choose from, the latest twelve scans and who is on duty.
     *
     * @return array<string, mixed>
     */
    public function scanner(User $guard): array
    {
        return [
            'gates' => array_map(fn (GateId $g) => ['id' => $g->value, 'name' => $g->label()], GateId::cases()),
            'recentScans' => AccessLogEntry::latest('occurred_at')->take(12)->get()->map(fn (AccessLogEntry $e) => [
                'id' => $e->id,
                'userName' => $e->user_name,
                'userRole' => $e->user_role,
                'result' => $e->result,
                'gate' => $e->gate,
                'occurredAt' => $e->occurred_at?->diffForHumans() ?? 'Just now',
                'denyReason' => $e->deny_reason,
            ]),
            'guard' => [
                'name' => $guard->display_name,
                'role' => $guard->role->value,
            ],
        ];
    }
}
