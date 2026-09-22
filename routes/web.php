<?php

use App\Http\Controllers\Web\AllocationController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentController;
use App\Http\Controllers\Web\ReferenceDataController;
use App\Http\Controllers\Web\IssuanceController;
use App\Http\Controllers\Web\ItemController;
use App\Http\Controllers\Web\QrController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\RequisitionController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\AssetScanController;
use App\Http\Controllers\Web\ForecastController;
use App\Http\Controllers\Web\FacilityController;
use App\Http\Controllers\Web\ActivityProposalController;
use App\Http\Controllers\Web\Fmo\FacilityItemController;
use App\Http\Controllers\Web\Fmo\FmoDashboardController;
use App\Http\Controllers\Web\Fmo\FmoReservationController;
use App\Http\Controllers\Web\Fmo\FmoRegistrationQrController;
use App\Http\Controllers\Web\Fmo\FmoUserController;
use App\Http\Controllers\Web\Fmo\FmoVenueController;
use App\Http\Controllers\Web\AccessVoucherController;
use App\Http\Controllers\Web\RegistrationQrController;
use App\Http\Controllers\Web\ReservationRebookingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/login', [AuthController::class, 'showLogin']);
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register/send-code', [AuthController::class, 'sendVerificationCode'])->name('register.send-code');
    Route::post('/register/verify-code', [AuthController::class, 'verifyCode'])->name('register.verify-code');
    Route::post('/register/verify-voucher', [AuthController::class, 'verifyVoucher'])->name('register.verify-voucher');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password/send-code', [AuthController::class, 'sendResetCode'])->name('password.send-code');
    Route::post('/forgot-password/verify-code', [AuthController::class, 'verifyResetCode'])->name('password.verify-code');
    Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
});

/*
 | Logout sits outside the "auth" group on purpose. When a session has already
 | expired, an auth-guarded logout bounced the request through the auth
 | redirect instead of simply finishing the sign-out, which is part of why the
 | Logout button sometimes ended on an error page.
 */
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware(['admin', 'asset_management']);
    Route::redirect('/home', '/dashboard');

    Route::get('/items', [ItemController::class, 'index'])->name('items.index')->middleware(['supply_access', 'asset_management']);
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create')->middleware(['admin', 'asset_management']);
    Route::post('/items', [ItemController::class, 'store'])->name('items.store')->middleware(['admin', 'asset_management']);
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show')->middleware(['supply_access', 'asset_management']);
    Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit')->middleware(['admin', 'asset_management']);
    Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update')->middleware(['admin', 'asset_management']);
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy')->middleware(['admin', 'asset_management']);

    Route::resource('departments', DepartmentController::class)->except(['show'])->middleware(['super_admin', 'asset_management']);
    Route::resource('suppliers', SupplierController::class)->except(['show'])->middleware(['admin', 'asset_management']);
    Route::resource('allocations', AllocationController::class)->except(['show'])->middleware(['admin', 'asset_management']);

    Route::middleware(['super_admin', 'asset_management'])->prefix('reference-data')->name('reference-data.')->group(function () {
        Route::get('/', [ReferenceDataController::class, 'index'])->name('index');
        Route::post('/floors', [ReferenceDataController::class, 'storeFloor'])->name('floors.store');
        Route::delete('/floors/{floor}', [ReferenceDataController::class, 'destroyFloor'])->name('floors.destroy');
        Route::post('/rooms', [ReferenceDataController::class, 'storeRoom'])->name('rooms.store');
        Route::delete('/rooms/{room}', [ReferenceDataController::class, 'destroyRoom'])->name('rooms.destroy');
        Route::post('/categories', [ReferenceDataController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [ReferenceDataController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [ReferenceDataController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('/asset-types', [ReferenceDataController::class, 'storeAssetType'])->name('asset-types.store');
        Route::delete('/asset-types/{assetType}', [ReferenceDataController::class, 'destroyAssetType'])->name('asset-types.destroy');
    });

    Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index')->middleware(['supply_access', 'asset_management']);
    Route::get('/requisitions/create', [RequisitionController::class, 'create'])->name('requisitions.create')->middleware(['supply_access', 'asset_management']);
    Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store')->middleware(['supply_access', 'asset_management']);
    Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])->name('requisitions.show')->middleware(['supply_access', 'asset_management']);
    Route::get('/requisitions/{requisition}/receipt', [RequisitionController::class, 'receipt'])->name('requisitions.receipt')->middleware(['supply_access', 'asset_management']);
    Route::middleware('approver')->group(function () {
        Route::post('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');
    });
    Route::delete('/requisitions/{requisition}', [RequisitionController::class, 'destroy'])->name('requisitions.destroy')->middleware(['supply_access', 'asset_management']);

    Route::get('/issuances', [IssuanceController::class, 'index'])->name('issuances.index')->middleware(['admin', 'asset_management']);
    Route::get('/issuances/create', [IssuanceController::class, 'create'])->name('issuances.create')->middleware(['admin', 'asset_management']);
    Route::post('/issuances', [IssuanceController::class, 'store'])->name('issuances.store')->middleware(['admin', 'asset_management']);
    Route::post('/issuances/{issuance}/return', [IssuanceController::class, 'returnItem'])->name('issuances.return')->middleware(['admin', 'asset_management']);
    Route::delete('/issuances/{issuance}', [IssuanceController::class, 'destroy'])->name('issuances.destroy');

    Route::get('/qr-scanner', [QrController::class, 'index'])->name('qr.index')->middleware(['admin', 'asset_management']);
    Route::get('/qr/print', [QrController::class, 'printBatch'])->name('qr.print')->middleware(['admin', 'asset_management']);
    Route::get('/qr/{item}/batch', [QrController::class, 'batch'])->name('qr.batch')->middleware(['admin', 'asset_management']);
    Route::get('/qr/{item}', [QrController::class, 'show'])->name('qr.show')->middleware(['admin', 'asset_management']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware(['admin', 'asset_management']);
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');



    /* ---------------------------------------------------------------
     | Reservation submission -- open to any signed-in requestor.
     | /facilities itself only redirects now (kept so old bookmarks and
     | previously-sent notification links never 404 or 403).
     * -------------------------------------------------------------- */
    Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
    Route::get('/facilities/reserve', [FacilityController::class, 'createReservation'])->name('facilities.reserve');
    Route::post('/facilities/reservations', [FacilityController::class, 'storeReservation'])->name('facilities.reservations.store');

    /* Legacy FMO approve/reject endpoints -- now backend-gated. */
    Route::middleware('fmo_access')->group(function () {
        Route::post('/facilities/reservations/{reservation}/approve', [FacilityController::class, 'approve'])->name('facilities.reservations.approve');
        Route::post('/facilities/reservations/{reservation}/reject', [FacilityController::class, 'reject'])->name('facilities.reservations.reject');
    });
    Route::delete('/facilities/reservations/{reservation}', [FacilityController::class, 'destroyReservation'])
        ->middleware('fmo_super_admin')->name('facilities.reservations.destroy');

    /* ---------------------------------------------------------------
     | FMO SUPER ADMIN / FMO STAFF AREA
     | Every route below is blocked for Asset Management Super Admin,
     | Asset Management Admin, approvers and requestors by fmo_access --
     | typing the URL by hand returns 403 before the controller runs.
     * -------------------------------------------------------------- */
    Route::middleware('fmo_access')->prefix('fmo')->name('fmo.')->group(function () {
        Route::get('/dashboard', [FmoDashboardController::class, 'index'])->name('dashboard');

        Route::get('/reservations', [FmoReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/{reservation}', [FmoReservationController::class, 'show'])->name('reservations.show');
        Route::post('/reservations/{reservation}/approve-venue', [FmoReservationController::class, 'approveVenue'])->name('reservations.approve-venue');
        Route::post('/reservations/{reservation}/approve', [FmoReservationController::class, 'approve'])->name('reservations.approve');
        Route::post('/reservations/{reservation}/reject', [FmoReservationController::class, 'reject'])->name('reservations.reject');

        /* Emergency cancellation of a fully approved booking, and the decision
           on whatever replacement schedule the requestor sends back. */
        Route::post('/reservations/{reservation}/emergency-cancel', [FmoReservationController::class, 'emergencyCancel'])->name('reservations.emergency-cancel');
        Route::post('/reservations/{reservation}/rebooking/approve', [FmoReservationController::class, 'approveRebooking'])->name('reservations.rebooking.approve');
        Route::post('/reservations/{reservation}/rebooking/decline', [FmoReservationController::class, 'declineRebooking'])->name('reservations.rebooking.decline');
        Route::delete('/reservations/{reservation}', [FmoReservationController::class, 'destroy'])
            ->middleware('fmo_super_admin')->name('reservations.destroy');

        Route::get('/venues', [FmoVenueController::class, 'index'])->name('venues.index');
        Route::get('/venues/create', [FmoVenueController::class, 'create'])->name('venues.create');
        Route::post('/venues', [FmoVenueController::class, 'store'])->name('venues.store');
        Route::get('/venues/{venue}', [FmoVenueController::class, 'show'])->name('venues.show');
        Route::get('/venues/{venue}/edit', [FmoVenueController::class, 'edit'])->name('venues.edit');
        Route::put('/venues/{venue}', [FmoVenueController::class, 'update'])->name('venues.update');
        Route::post('/venues/{venue}/toggle', [FmoVenueController::class, 'toggle'])->name('venues.toggle');
        Route::delete('/venues/{venue}', [FmoVenueController::class, 'destroy'])
            ->middleware('fmo_super_admin')->name('venues.destroy');

        Route::get('/items', [FacilityItemController::class, 'index'])->name('items.index');
        Route::post('/items', [FacilityItemController::class, 'store'])->name('items.store');
        Route::get('/services', [FacilityItemController::class, 'index'])->name('services.index');
        Route::post('/services', [FacilityItemController::class, 'store'])->name('services.store');
        Route::put('/catalog/{facilityItem}', [FacilityItemController::class, 'update'])->name('catalog.update');
        Route::post('/catalog/{facilityItem}/toggle', [FacilityItemController::class, 'toggle'])->name('catalog.toggle');
        Route::delete('/catalog/{facilityItem}', [FacilityItemController::class, 'destroy'])
            ->middleware('fmo_super_admin')->name('catalog.destroy');

        /* Printable registration QR for the FMO office door -- FMO side only. */
        Route::get('/registration-qr', [FmoRegistrationQrController::class, 'index'])->name('registration-qr');

        /* FMO user management -- FMO Super Admin only. */
        Route::middleware('fmo_super_admin')->group(function () {
            Route::get('/users', [FmoUserController::class, 'index'])->name('users.index');
            Route::post('/users', [FmoUserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [FmoUserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/signature', [FmoUserController::class, 'updateSignature'])->name('users.signature');
            Route::post('/users/{user}/toggle', [FmoUserController::class, 'toggle'])->name('users.toggle');
            Route::post('/users/{user}/reset-password', [FmoUserController::class, 'resetPassword'])->name('users.reset-password');
            Route::delete('/users/{user}', [FmoUserController::class, 'destroy'])->name('users.destroy');
        });
    });

    /* The requestor's answer to an emergency cancellation: pick a new date or
       a different venue. Ownership is checked inside the controller. */
    Route::get('/my-bookings/{reservation}/reschedule', [ReservationRebookingController::class, 'edit'])->name('reservations.rebook.edit');
    Route::post('/my-bookings/{reservation}/reschedule', [ReservationRebookingController::class, 'update'])->name('reservations.rebook.update');

    Route::get('/activity-proposals', [ActivityProposalController::class, 'index'])->name('activity-proposals.index');
    Route::get('/activity-proposals/create', [ActivityProposalController::class, 'create'])->name('activity-proposals.create');
    Route::post('/activity-proposals', [ActivityProposalController::class, 'store'])->name('activity-proposals.store');
    Route::get('/activity-proposals/{activityProposal}', [ActivityProposalController::class, 'show'])->name('activity-proposals.show');
    Route::get('/activity-proposals/{activityProposal}/program-flow-file', [ActivityProposalController::class, 'programFlowFile'])->name('activity-proposals.program-flow-file');
    Route::post('/activity-proposals/{activityProposal}/approve-adviser', [ActivityProposalController::class, 'approveAdviser'])->name('activity-proposals.approve-adviser');
    Route::post('/activity-proposals/{activityProposal}/sign-dean', [ActivityProposalController::class, 'signDean'])->name('activity-proposals.sign-dean');
    Route::post('/activity-proposals/{activityProposal}/sign-sdao', [ActivityProposalController::class, 'signSdao'])->name('activity-proposals.sign-sdao');
    Route::post('/activity-proposals/{activityProposal}/sign-facilities', [ActivityProposalController::class, 'signFacilities'])->name('activity-proposals.sign-facilities');
    Route::post('/activity-proposals/{activityProposal}/sign-academic-director', [ActivityProposalController::class, 'signAcademicDirector'])->name('activity-proposals.sign-academic-director');
    Route::post('/activity-proposals/{activityProposal}/approve-executive', [ActivityProposalController::class, 'approveExecutive'])->name('activity-proposals.approve-executive');
    Route::post('/activity-proposals/{activityProposal}/reject', [ActivityProposalController::class, 'reject'])->name('activity-proposals.reject');
    Route::delete('/activity-proposals/{activityProposal}', [ActivityProposalController::class, 'destroy'])->name('activity-proposals.destroy');

    Route::middleware('asset_management')->group(function () {
        Route::get('/forecasting', [ForecastController::class, 'index'])->name('forecast.index');
        Route::post('/forecasting/usage-logs', [ForecastController::class, 'storeUsageLog'])->name('forecast.usage-logs.store');
        Route::delete('/forecasting/usage-logs/{usageLog}', [ForecastController::class, 'destroyUsageLog'])->name('forecast.usage-logs.destroy');
        Route::get('/asset-scans', [AssetScanController::class, 'index'])->name('asset-scans.index');
        Route::get('/asset-scans/print', [AssetScanController::class, 'print'])->name('asset-scans.print');
        Route::post('/asset-scans/{assetScanLog}/resolve', [AssetScanController::class, 'resolve'])->name('asset-scans.resolve');
        Route::delete('/asset-scans/{assetScanLog}', [AssetScanController::class, 'destroy'])->name('asset-scans.destroy');
    });

    Route::middleware(['admin', 'asset_management'])->group(function () {
        Route::get('/access-vouchers', [AccessVoucherController::class, 'index'])->name('access-vouchers.index');
        /* Printable registration QR for the Asset Management office door. */
        Route::get('/registration-qr', [RegistrationQrController::class, 'index'])->name('registration-qr');
        Route::post('/access-vouchers', [AccessVoucherController::class, 'store'])->name('access-vouchers.store');
        Route::post('/access-vouchers/{accessVoucher}/revoke', [AccessVoucherController::class, 'revoke'])->name('access-vouchers.revoke');
        Route::delete('/access-vouchers/{accessVoucher}', [AccessVoucherController::class, 'destroy'])->name('access-vouchers.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/signature', [AdminUserController::class, 'updateSignature'])->name('users.signature');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    });
});