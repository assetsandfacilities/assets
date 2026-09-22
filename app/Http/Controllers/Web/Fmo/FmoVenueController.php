<?php

namespace App\Http\Controllers\Web\Fmo;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Full venue CRUD for the FMO Super Admin. Venues saved here appear
 * immediately as options on the Reservation Request and Activity Proposal
 * forms (both read Facility::active()).
 */
class FmoVenueController extends Controller
{
    private function rules(?Facility $facility = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:facilities,code' . ($facility ? ',' . $facility->id : '')],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'resources' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $venues = Facility::query()
            ->withCount('reservations')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('fmo.venues.index', compact('venues', 'search', 'status'));
    }

    public function create(): View
    {
        return view('fmo.venues.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);
        Facility::create($data);

        return redirect()->route('fmo.venues.index')->with('success', 'Venue added. It is now selectable on the Reservation Request form.');
    }

    public function show(Facility $venue): View
    {
        $venue->loadCount('reservations');
        $recent = $venue->reservations()->with(['user', 'reviewer'])->latest('start_at')->limit(10)->get();

        return view('fmo.venues.show', compact('venue', 'recent'));
    }

    public function edit(Facility $venue): View
    {
        return view('fmo.venues.edit', ['facility' => $venue]);
    }

    public function update(Request $request, Facility $venue): RedirectResponse
    {
        $data = $request->validate($this->rules($venue));
        $data['is_active'] = $request->boolean('is_active', false);
        $venue->update($data);

        return redirect()->route('fmo.venues.index')->with('success', 'Venue updated successfully.');
    }

    /** Activate / deactivate without touching any reservation history. */
    public function toggle(Facility $venue): RedirectResponse
    {
        $venue->update(['is_active' => !$venue->is_active]);

        return back()->with('success', 'Venue "' . $venue->name . '" is now ' . ($venue->is_active ? 'active' : 'inactive') . '.');
    }

    /**
     * Deletion is protected: a venue that is already attached to reservation
     * records can never be removed, because that would take historical
     * reservations down with it. Deactivate instead.
     */
    public function destroy(Facility $venue): RedirectResponse
    {
        $linked = $venue->reservations()->count();

        if ($linked > 0) {
            return back()->withErrors([
                'venue' => 'This venue is linked to ' . $linked . ' reservation record(s) and cannot be deleted. Deactivate it instead so it disappears from the Reservation form while the history stays intact.',
            ]);
        }

        if (\App\Models\ActivityProposal::where('facility_id', $venue->id)->exists()) {
            return back()->withErrors([
                'venue' => 'This venue is referenced by existing Activity Proposals and cannot be deleted. Deactivate it instead.',
            ]);
        }

        $venue->delete();

        return redirect()->route('fmo.venues.index')->with('success', 'Venue deleted successfully.');
    }
}
