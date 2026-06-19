<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ProviderRefundRequest;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class ProviderRefundApiController extends Controller
{
    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * [POST] Refund Purchased Products on Batches (Back to Provider)
     */
    public function __invoke(ProviderRefundRequest $request): JsonResponse
    {
        try {
            $refund = $this->purchaseService->refundToProvider($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Purchased products refunded to provider successfully.',
                'data' => $refund,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
