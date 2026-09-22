<?php

namespace App\Http\Controllers\Web\Fmo;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityItem;
use App\Models\FacilityReservation;
use App\Models\User;
use Illuminate\View\View;

/**
 * Landing page for the FMO Super Admin / FMO staff. Contains Facilities data
 * only -- no CAPEX, OPEX, requisition, issuance or supplier figures.
 */
class FmoDashboardController extends Controller
{
    public function index(): View
    {
        $reservations = FacilityReservation::query();

        $stats = [
            'venues_total' => Facility::count(),
            'venues_active' => Facility::where('is_active', true)->count(),
            'pending' => (clone $reservations)->where('status', 'pending')->count(),
            'approved' => (clone $reservations)->where('status', 'approved')->count(),
            'rejected' => (clone $reservations)->where('status', 'rejected')->count(),
            'upcoming' => (clone $reservations)->where('status', 'approved')->where('start_at', '>=', now())->count(),
            'items' => FacilityItem::items()->count(),
            'services' => FacilityItem::services()->count(),
            'fmo_users' => User::query()->facilitiesSide()->count(),
        ];

        $pendingQueue = FacilityReservation::with(['facility', 'user', 'activityProposal'])
            ->where('status', 'pending')
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        $upcoming = FacilityReservation::with(['facility', 'user'])
            ->where('status', 'approved')
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        return view('fmo.dashboard', compact('stats', 'pendingQueue', 'upcoming'));
    }
}
