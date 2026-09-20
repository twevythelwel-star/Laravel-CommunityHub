<?php

namespace App\Http\Controllers\Api;

use App\Enums\GateId;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLogEntry;
use App\Models\GatePass;
use App\Services\GatePassEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mobile and scanner gate-pass endpoints.
 *
 * validateToken() is the endpoint a handheld scanner calls. It runs the same
 * GatePassEngine used by the web scanner, so the two cannot drift apart, and it
 * writes the access-log entry in the same transaction as the decision.
 */
class GatePassApiController extends Controller
{
    public function __construct(private readonly GatePassEngine $engine) {}

    public function show(Request $request): JsonResponse
    {
        $pass = $this->engine->issuePassFor($request->user());
        $category = $pass->category;

        return response()->json([
            'pass' => [
                'passId' => $pass->pass_id,
                'category' => $category->value,
                'holderName' => $pass->holder_name,
                'property' => $pass->property,
                'accessZone' => $pass->access_zone,
                'gate' => $pass->designated_gate->value,
                'rotationSeq' => $pass->rotation_seq,
                'status' => $pass->status,
            ],
            'visual' => [
                'config' => $this->engine->categoryConfig($category),
                'variant' => $this->engine->assignedColorVariant($category, $pass->pass_id, $pass->rotation_seq),
                'shape' => $category->shape()->value,
            ],
            'policy' => $this->engine->policyFor($category),
        ]);
    }

    public function token(Request $request): JsonResponse
    {
        $pass = $this->engine->issuePassFor($request->user());
        $issued = $this->engine->issueToken($pass);

        return response()->json([
            'token' => $issued['token'],
            'validFrom' => $issued['valid_from']->toIso8601String(),
            'validUntil' => $issued['valid_until']->toIso8601String(),
            'secondsRemaining' => max(0, $issued['valid_until']->getTimestamp() - now()->getTimestamp()),
        ]);
    }

    public function rotate(Request $request): JsonResponse
    {
        $pass = $this->engine->issuePassFor($request->user());
        $rotation = $this->engine->rotateVisualIdentity($pass);

        return response()->json([
            'rotationSeq' => $rotation['next_seq'],
            'variant' => $rotation['variant'],
            'rotatedAt' => $rotation['rotated_at'],
        ]);
    }

    public function validateToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'gate' => ['nullable', 'string', 'in:GATE-01,GATE-02,GATE-ANY'],
        ]);

        $gate = GateId::tryFrom($validated['gate'] ?? '') ?? GateId::Gate01;
        $report = $this->engine->validate($validated['token'], $gate);

        $entry = DB::transaction(function () use ($report, $gate, $request) {
            $pass = GatePass::where('pass_id', $report['passId'])->first();

            return AccessLogEntry::create([
                'user_id' => $pass?->user_id,
                'user_name' => $report['userName'],
                'user_role' => $pass?->user?->role->value ?? $report['category'],
                'method' => 'Digital Pass',
                'gate' => config('gatepass.gates.'.$gate->value, $gate->value),
                'pass_id' => $report['passId'],
                'result' => $report['status'],
                'deny_reason' => $report['status'] === ValidationStatus::Deny->value
                    ? $report['primaryReason']
                    : null,
                'validation_report' => $report,
                'scanned_by' => $request->user()->id,
                'occurred_at' => now(),
            ]);
        });

        if ($report['status'] === ValidationStatus::Deny->value) {
            Log::channel('security')->warning('Gate pass denied (API)', [
                'pass_id' => $report['passId'],
                'gate' => $gate->value,
                'reason' => $report['primaryReason'],
                'scanned_by' => $request->user()->uid,
            ]);
        }

        return response()->json([
            'allowed' => $report['status'] === ValidationStatus::Allow->value,
            'report' => $report,
            'accessLogId' => $entry->id,
        ]);
    }
}
