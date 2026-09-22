<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RequisitionController;
use App\Http\Controllers\Api\IssuanceController;
use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\AssetScanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ErpRequisitionController;

Route::name('api.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');


    // Read-only machine-to-machine Procurement/ERP integration.
    // Authentication is independent from mobile user JWTs and uses a dedicated
    // X-ERP-API-Key plus optional IP allow-listing.
    Route::prefix('erp')->middleware(['erp.api', 'throttle:60,1'])->group(function () {
        Route::get('/requisitions', [ErpRequisitionController::class, 'index'])->name('erp.requisitions.index');
        Route::get('/requisitions/export.csv', [ErpRequisitionController::class, 'csv'])->name('erp.requisitions.csv');
    });

    // Everything below requires a valid JWT. Browsing/reading item, requisition, and
    // scan data unauthenticated was a real gap in the previous version of this API.
    Route::middleware('auth.jwt')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

        Route::apiResource('items', ItemController::class);
        Route::get('/scan/{code}', [ItemController::class, 'lookupByCode'])->name('items.scan');
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('allocations', AllocationController::class);

        Route::get('/facilities', function () {
            return \App\Models\Facility::where('is_active', true)->orderBy('name')->get();
        })->name('facilities.index');

        // Real Floor -> Room hierarchy (same data shown in the web system's Reference
        // Data page). The mobile app uses this to let the housekeeper pick their Floor
        // and Room BEFORE scanning a QR code (replaces the old GPS-based verification).
        Route::get('/floors', function () {
            return \App\Models\Floor::with(['rooms' => function ($q) {
                $q->orderBy('name');
            }])->orderBy('sort_order')->orderBy('name')->get();
        })->name('floors.index');

        Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
        Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
        Route::get('/requisitions/{id}', [RequisitionController::class, 'show'])->name('requisitions.show');
        Route::post('/requisitions/{id}/approve', [RequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('/requisitions/{id}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');

        Route::get('/issuances', [IssuanceController::class, 'index'])->name('issuances.index');
        Route::post('/issuances', [IssuanceController::class, 'store'])->name('issuances.store');
        Route::post('/issuances/{id}/return', [IssuanceController::class, 'returnItem'])->name('issuances.return');

        // Mobile-only: asset scanning (floor/room picked before scan) + mismatch detection/resolution.
        Route::get('/scans', [AssetScanController::class, 'index'])->name('scans.index');
        Route::post('/scans', [AssetScanController::class, 'store'])->name('scans.store');
        Route::post('/scans/{assetScanLog}/resolve', [AssetScanController::class, 'resolve'])->name('scans.resolve');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    });
});
