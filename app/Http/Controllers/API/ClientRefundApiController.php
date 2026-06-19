<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ClientRefundRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class ClientRefundApiController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * [POST] Refund Client Orders
     */
    public function __invoke(ClientRefundRequest $request): JsonResponse
    {
        try {
            $refund = $this->orderService->refundFromClient($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Client refund processed successfully and stock returned.',
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
