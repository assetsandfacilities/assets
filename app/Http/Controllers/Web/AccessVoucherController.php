<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccessVoucher;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessVoucherController extends Controller
{
    public function index(Request $request): View
    {
        $vouchers = AccessVoucher::query()
            ->with(['department', 'generator', 'usedBy'])
            ->latest()
            ->paginate(20);

        return view('access-vouchers.index', [
            'vouchers' => $vouchers,
            'departments' => Department::orderBy('name')->get(),
            'approverTypes' => ['adviser', 'dean', 'sdao', 'academic_director', 'executive'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'voucher_type' => ['required', Rule::in(['requestor', 'approver'])],
            'approver_type' => ['nullable', 'required_if:voucher_type,approver', Rule::in(['adviser', 'dean', 'sdao', 'academic_director', 'executive'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            'expires_in_hours' => ['required', 'integer', Rule::in([24, 72, 168])],
        ]);

        $prefix = match ($data['voucher_type']) {
            'requestor' => 'REQ',
            'approver' => 'APR',
        };
        $plain = $prefix.'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));

        AccessVoucher::create([
            'code_hash' => AccessVoucher::hashCode($plain),
            'code_hint' => $prefix.'-****-'.substr($plain, -4),
            'voucher_type' => $data['voucher_type'],
            'approver_type' => $data['voucher_type'] === 'approver' ? $data['approver_type'] : null,
            // Campus-wide approver roles are never department-bound, so a
            // department chosen by mistake on the voucher form is dropped
            // rather than carried into the new account at signup.
            'department_id' => \App\Support\SignatoryResolver::isCampusWide($data['approver_type'] ?? null)
                ? null
                : ($data['department_id'] ?? null),
            'generated_by' => auth()->id(),
            'expires_at' => now()->addHours((int) $data['expires_in_hours']),
        ]);

        return back()->with('success', 'Voucher generated. Copy it now — the full code is shown only once.')
            ->with('generated_voucher', $plain);
    }

    public function revoke(AccessVoucher $accessVoucher): RedirectResponse
    {
        if ($accessVoucher->used_at) {
            return back()->withErrors(['voucher' => 'A used voucher cannot be revoked.']);
        }
        if (!$accessVoucher->revoked_at) {
            $accessVoucher->update(['revoked_at' => now(), 'revoked_by' => auth()->id()]);
        }
        return back()->with('success', 'Voucher revoked.');
    }

    public function destroy(AccessVoucher $accessVoucher): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Only the Asset Management Super Admin can delete voucher history.');

        $accessVoucher->delete();

        return back()->with('success', 'Voucher history deleted successfully.');
    }
}
