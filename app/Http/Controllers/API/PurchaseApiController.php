<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\PurchaseRequest;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class PurchaseApiController extends Controller
{
    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * [POST] Purchase Products and Add to Storage
     */
    public function __invoke(PurchaseRequest $request): JsonResponse
    {
        try {
            $batch = $this->purchaseService->purchase($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Products purchased and added to storage successfully.',
                'data' => $batch,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
