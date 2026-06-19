<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\InventoryReportService;
use Illuminate\Http\JsonResponse;

class BatchProfitApiController extends Controller
{
    protected InventoryReportService $reportService;

    public function __construct(InventoryReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * [GET] Calculate Profit per Batch
     */
    public function __invoke(): JsonResponse
    {
        $profit = $this->reportService->getBatchProfit();

        return response()->json($profit, 200);
    }
}
