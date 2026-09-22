<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\SignatureAudit;
use App\Support\SignatureData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    /**
     * Roles the Asset Management Super Admin / Admin may assign. The FMO roles
     * are deliberately absent, so a forged POST cannot mint an
     * fmo_super_admin (or an fmo staff account) from this side of the system.
     */
    public const ASSIGNABLE_ROLES = ['super_admin', 'admin', 'approver', 'requestor'];

    /**
     * Rejects any write aimed at an FMO account. Without this an edited form
     * request could target an FMO user id even though the list never shows it.
     */
    private function guardTarget(User $user): void
    {
        $isAssetSide = User::query()->assetManagementSide()->whereKey($user->id)->exists();
        abort_unless($isAssetSide, 403, 'This account belongs to Facilities Management and cannot be managed from Asset Management.');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));

        // Backend-level separation: the Asset Management Users tab never lists
        // FMO Super Admin or FMO staff accounts -- those belong to the FMO
        // Super Admin's own Users page.
        $users = User::query()
            ->assetManagementSide()
            ->with(['department', 'signatureAudits.changer'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('approver_type', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN NOT is_approved THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 WHEN role = 'approver' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(),
            'roles' => self::ASSIGNABLE_ROLES,
            'approverTypes' => ['dean', 'executive', 'adviser', 'sdao', 'academic_director'],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Polite title printed with the name on charge slips and proposals.
            // Editable here so accounts created before titles existed can get one.
            'title' => ['nullable', 'string', Rule::in(User::politeTitles())],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'approver_type' => ['nullable', Rule::in(['dean', 'executive', 'adviser', 'sdao', 'academic_director'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        if ($user->id === auth()->id() && in_array($user->role, ['admin', 'super_admin'], true) && $data['role'] !== $user->role) {
            return back()->withErrors(['role' => 'You cannot remove your own admin access.']);
        }

        if ($user->role === 'admin' && $data['role'] !== 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'At least one Asset Management Admin account must remain in the system.']);
            }
        }

        if ($user->role === 'super_admin' && $data['role'] !== 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->withErrors(['role' => 'The Super Admin role cannot be removed — there must always be exactly one Super Admin account.']);
            }
        }

        if ($data['role'] === 'super_admin' && $user->role !== 'super_admin') {
            if (User::where('role', 'super_admin')->exists()) {
                return back()->withErrors(['role' => 'A Super Admin account already exists. There can only be one.']);
            }
        }

        if (in_array($data['role'], ['super_admin', 'admin', 'approver'], true) && !$user->hasSignature()) {
            return back()->withErrors(['signature_file' => 'This approval-capable account needs an e-signature before assigning that role. The Asset Management Super Admin can set it below under E-Signature Management.']);
        }

        if ($data['role'] !== 'approver') {
            $data['approver_type'] = null;
        } elseif (($data['approver_type'] ?? null) === 'sdao') {
            // SDAO is a school-wide office in this deployment, not a department.
            $data['department_id'] = null;
        } elseif (\App\Support\SignatoryResolver::isCampusWide($data['approver_type'] ?? null)) {
            /*
             | Academic Director and Executive Director cover the whole campus
             | and hold no department, which is exactly why forms auto-assign
             | them instead of asking the requestor to choose. Two guards keep
             | that assumption true: the department is cleared, and a second
             | active holder is refused -- otherwise "the" Academic Director
             | would be ambiguous and routing would silently pick one of them.
             */
            $type = $data['approver_type'];
            $data['department_id'] = null;

            if ($request->boolean('is_approved') && \App\Support\SignatoryResolver::isTaken($type, $user->id)) {
                return back()->withErrors([
                    'approver_type' => 'An active '.\App\Support\SignatoryResolver::label($type)
                        .' already exists. There can only be one for the whole campus — deactivate the current one first.',
                ]);
            }
        }

        // Keep the stored account type meaningful when an Asset admin changes
        // the role of an older account. Asset-side requestors use the unified
        // Requestor account type.
        $data['access_scope'] = 'asset';
        if ($data['role'] === 'super_admin') {
            $data['account_type'] = 'asset_super_admin';
        } elseif ($data['role'] === 'admin') {
            $data['account_type'] = 'asset_admin';
        } elseif ($data['role'] === 'approver') {
            $data['account_type'] = 'approver';
        } elseif ($data['role'] === 'requestor') {
            $data['account_type'] = 'requestor';
        }

        $data['is_approved'] = $request->boolean('is_approved');
        $data['approved_at'] = $data['is_approved'] ? ($user->approved_at ?? now()) : null;

        $user->update($data);

        return back()->with('success', 'User profile and role updated successfully.');
    }

    public function updateSignature(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only the Asset Management Super Admin can replace e-signatures.');
        $this->guardTarget($user);

        $request->validate([
            'signature_file' => ['nullable', 'file', 'max:2048'],
            'signature_drawn' => ['nullable', 'string'],
        ]);
        $signature = SignatureData::fromRequest($request, true);
        $previous = $user->signature_data;

        $user->update([
            'signature_data' => $signature,
            'signature_updated_at' => now(),
            'signature_updated_by' => auth()->id(),
        ]);

        SignatureAudit::create([
            'user_id' => $user->id,
            'changed_by' => auth()->id(),
            'source' => 'asset_super_admin',
            'previous_hash' => $previous ? hash('sha256', $previous) : null,
            'new_hash' => hash('sha256', $signature),
            'changed_at' => now(),
        ]);

        return back()->with('success', 'E-signature updated for ' . $user->name . '. The change was recorded in the signature audit log.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->guardTarget($user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account while logged in.']);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['user' => 'At least one Asset Management Admin account must remain in the system.']);
        }

        if ($user->role === 'super_admin' && User::where('role', 'super_admin')->count() <= 1) {
            return back()->withErrors(['user' => 'The Super Admin account cannot be deleted — there must always be exactly one.']);
        }

        $user->delete();

        return back()->with('success', 'User account deleted successfully.');
    }
}
