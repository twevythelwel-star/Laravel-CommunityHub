<?php

namespace App\Http\Controllers\Api;

use App\Actions\Visitors\CheckInVisitorAction;
use App\Actions\Visitors\RegisterVisitorAction;
use App\Enums\GateId;
use App\Exceptions\ScanNotConfirmable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visitors\CheckInApiVisitorRequest;
use App\Http\Requests\Visitors\CheckOutApiVisitorRequest;
use App\Http\Requests\Visitors\StoreApiVisitorRequest;
use App\Http\Resources\VisitorResource;
use App\Models\Visitor;
use App\Services\GateScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VisitorApiController extends Controller
{
    public function __construct(
        private readonly GateScanner $scanner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $visitors = Visitor::query()
            ->when(! $user->can('manageSecurity'), fn ($q) => $q->where('homeowner_id', $user->id))
            ->when($request->string('status')->isNotEmpty(),
                fn ($q) => $q->where('status', $request->string('status')))
            ->latest('expected_at')
            ->limit(100)
            ->get();

        return response()->json(['visitors' => VisitorResource::collection($visitors)->resolve()]);
    }

    /**
     * Same rules as the dashboard (RegisterVisitorAction): blocklist, a
     * temporary homeowner's stay dates, the visit's property, notifications.
     */
    public function store(StoreApiVisitorRequest $request, RegisterVisitorAction $register): JsonResponse
    {
        ['visitor' => $visitor, 'pass' => $pass] = $register->execute($request->validated(), $request->user());

        return response()->json([
            'visitor' => ['id' => $visitor->id],
            'pass' => ['passId' => $pass->pass_id, 'status' => $pass->status->value],
        ], 201);
    }

    /**
     * Same rules as the dashboard (CheckInVisitorAction): refuses an expired
     * clearance, a second check-in and a blocklisted visitor (logging the
     * denial). Answers {allowed, reason} as gate tablets expect.
     */
    public function checkIn(CheckInApiVisitorRequest $request, Visitor $visitor, CheckInVisitorAction $checkIn): JsonResponse
    {
        try {
            $checkIn->execute($visitor, $request->user(), $this->gateFrom($request), $request->string('gate')->toString() ?: 'Main Gate');
        } catch (ValidationException $e) {
            return response()->json(['allowed' => false, 'reason' => collect($e->errors())->flatten()->first()], 422);
        } catch (ScanNotConfirmable $e) {
            return response()->json(['allowed' => false, 'reason' => $e->getMessage()], 422);
        }

        return response()->json(['allowed' => true, 'status' => $visitor->fresh()->status->value]);
    }

    public function checkOut(CheckOutApiVisitorRequest $request, Visitor $visitor): JsonResponse
    {
        if ($visitor->gatePass) {
            try {
                $this->scanner->manualCheckOut($visitor->gatePass, $request->user(), $this->gateFrom($request));
            } catch (ScanNotConfirmable $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['status' => $visitor->fresh()->status->value]);
        }

        $visitor->checkOut();

        return response()->json(['status' => $visitor->status->value]);
    }

    private function gateFrom(Request $request): GateId
    {
        return GateId::tryFrom($request->string('gate')->toString()) ?? GateId::Gate01;
    }
}
