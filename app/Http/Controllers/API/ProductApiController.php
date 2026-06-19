<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\InventoryReportService;
use Illuminate\Http\JsonResponse;

class ProductApiController extends Controller
{
    protected InventoryReportService $reportService;

    public function __construct(InventoryReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * [GET] Fetch Available Products for Ordering
     */
    public function available(): JsonResponse
    {
        $products = $this->reportService->getAvailableProducts();

        return response()->json($products, 200);
    }
}
