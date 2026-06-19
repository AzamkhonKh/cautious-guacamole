<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\StorageQuantitiesRequest;
use App\Services\InventoryReportService;
use Illuminate\Http\JsonResponse;

class StorageApiController extends Controller
{
    protected InventoryReportService $reportService;

    public function __construct(InventoryReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * [GET] Calculate Remaining Quantities in Storage by Date
     */
    public function remainingQuantities(StorageQuantitiesRequest $request): JsonResponse
    {
        $date = $request->input('date');
        $quantities = $this->reportService->getStorageQuantities($date);

        return response()->json($quantities, 200);
    }
}
