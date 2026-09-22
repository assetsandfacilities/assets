<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Roles that belong to the Facilities (FMO) side of the system. */
    public const FMO_ROLES = ['fmo_super_admin', 'fmo'];

    /** Roles that belong to the Asset Management side of the system. */
    public const ASSET_ROLES = ['super_admin', 'admin'];

    protected $fillable = ['department_id', 'name', 'title', 'email', 'password', 'role', 'account_type', 'access_scope', 'approver_type', 'is_approved', 'approved_at', 'email_verified_at', 'signature_data', 'signature_updated_at', 'signature_updated_by'];
    protected $hidden = ['password', 'remember_token', 'signature_data', 'jwt_revoked_before'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'signature_updated_at' => 'datetime',
        'jwt_revoked_before' => 'datetime',
        'is_approved' => 'boolean',
        // Panel security revision: e-signatures are ciphertext at rest in the DB.
        'signature_data' => 'encrypted',
    ];

    /**
     * The name as it should appear on a printed document: the polite title
     * followed by the name ("Ms. Ana Bautista"). Accounts without a title --
     * students, and anything created before titles existed -- fall back to the
     * plain name, so nothing needs backfilling.
     */
    public function getDisplayNameAttribute(): string
    {
        $title = trim((string) ($this->title ?? ''));

        return $title === '' ? (string) $this->name : $title.' '.$this->name;
    }

    /** Titles offered at registration; extend the list in config/nu.php. */
    public static function politeTitles(): array
    {
        return array_values(array_filter((array) config('nu.polite_titles', ['Mr.', 'Ms.', 'Mrs.'])));
    }

    public function department() { return $this->belongsTo(Department::class); }

    public function facilityReservations() { return $this->hasMany(FacilityReservation::class); }

    public function activityProposals() { return $this->hasMany(ActivityProposal::class); }

    public function signatureAudits() { return $this->hasMany(SignatureAudit::class)->latest('changed_at'); }
    public function signatureUpdater() { return $this->belongsTo(User::class, 'signature_updated_by'); }
    public function hasSignature(): bool { return filled($this->signature_data); }

    public function isPendingApproval(): bool
    {
        return !$this->is_approved;
    }

    /**
     * Human-readable account type used by admin user-management screens.
     * Older rows are inferred from their real role/scope so the UI never shows
     * a generic "Legacy" account type. New registrations store account_type
     * explicitly (student, requestor, approver, etc.).
     */
    public function accountTypeLabel(): string
    {
        $type = $this->account_type;

        if (!$type) {
            $type = match ($this->role) {
                'super_admin' => 'asset_super_admin',
                'admin' => 'asset_admin',
                'approver' => 'approver',
                'fmo_super_admin' => 'fmo_super_admin',
                'fmo' => 'fmo_staff',
                'housekeeping' => 'housekeeping',
                'requestor' => $this->access_scope === 'fmo' ? 'student' : 'requestor',
                default => $this->role ?: 'staff',
            };
        }

        return match ($type) {
            'asset_super_admin' => 'Asset Management Super Admin',
            'asset_admin' => 'Asset Management Admin',
            'fmo_super_admin' => 'FMO Super Admin',
            'fmo_staff' => 'FMO Staff',
            'student' => 'Student',
            'requestor' => 'Requestor',
            'staff' => 'Requestor',
            'organization' => 'Requestor',
            'approver' => 'Approver',
            'housekeeping' => 'Housekeeping',
            default => ucwords(str_replace('_', ' ', (string) $type)),
        };
    }

    /**
     * Asset Management administrator check. Deliberately does NOT include the
     * FMO Super Admin -- that account is confined to the Facilities side.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'super_admin';
    }

    public function isAssetManagementAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** The Asset Management Super Admin. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** The new Facilities Management Office Super Admin. */
    public function isFmoSuperAdmin(): bool
    {
        return $this->role === 'fmo_super_admin';
    }

    public function isApprover(): bool
    {
        return $this->role === 'approver';
    }

    public function isRequestor(): bool
    {
        return $this->role === 'requestor';
    }

    public function isFmo(): bool
    {
        return $this->role === 'fmo';
    }

    /** Any Facilities-side staff account (FMO Super Admin or FMO staff). */
    public function isFmoSide(): bool
    {
        return in_array($this->role, self::FMO_ROLES, true);
    }

    public function isHousekeeping(): bool
    {
        return $this->role === 'housekeeping';
    }

    public function isDeanApprover(): bool
    {
        return $this->isApprover() && $this->approver_type === 'dean';
    }

    public function isExecutiveApprover(): bool
    {
        return $this->isApprover() && $this->approver_type === 'executive';
    }

    public function isAdviserApprover(): bool
    {
        return $this->isApprover() && $this->approver_type === 'adviser';
    }

    public function isSdaoApprover(): bool
    {
        return $this->isApprover() && $this->approver_type === 'sdao';
    }

    public function isAcademicDirectorApprover(): bool
    {
        return $this->isApprover() && $this->approver_type === 'academic_director';
    }

    public function homeRouteName(): string
    {
        if ($this->isFmoSide()) {
            return 'fmo.dashboard';
        }
        if ($this->isSuperAdmin() || $this->isAssetManagementAdmin()) {
            return 'dashboard';
        }
        if ($this->isHousekeeping()) {
            return 'asset-scans.index';
        }
        if ($this->isAdviserApprover() || $this->isSdaoApprover() || $this->isAcademicDirectorApprover()) {
            return 'activity-proposals.index';
        }
        if ($this->isDeanApprover() || $this->isExecutiveApprover()) {
            return 'requisitions.index';
        }
        if ($this->isRequestor()) {
            return $this->canRequestSupplies() ? 'items.index' : 'activity-proposals.index';
        }
        return 'dashboard';
    }

    public function canManageInventory(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Whether this user should see/use the OPEX Inventory and Requisitions
     * (supply charge slip) features at all. False for requestors whose
     * department is flagged as facility-only (e.g. FMO) -- they use Activity
     * Proposals / Facility Reservations instead and never need to request
     * office supplies through this flow.
     */
    public function canRequestSupplies(): bool
    {
        if (!$this->isRequestor()) {
            return false;
        }

        // New registrations are explicit: students are Facilities-only while
        // Asset-side requestors use the Requestor account type. Older Staff/Organization
        // rows are also treated as Asset requestors for backward compatibility.
        if ($this->account_type === 'student') {
            return false;
        }
        if (in_array($this->account_type, ['requestor', 'staff', 'organization'], true)) {
            return true;
        }

        return !optional($this->department)->restrict_supply_requests;
    }

    public function canViewReports(): bool
    {
        return $this->isAdmin();
    }

    public function canUseQrScanner(): bool
    {
        return $this->isAdmin() || $this->isHousekeeping();
    }

    /**
     * Facilities Management access.
     *
     * The Asset Management Super Admin used to be included here. It is
     * deliberately removed: Asset Management and Facilities are now two fully
     * separated administrative domains.
     */
    public function canManageFacilities(): bool
    {
        return $this->isFmoSide();
    }

    /** Venue / item / service catalogue maintenance. */
    public function canManageFacilityCatalog(): bool
    {
        return $this->isFmoSide();
    }

    /** Destructive facilities actions are reserved for the FMO Super Admin. */
    public function canDeleteFacilityRecords(): bool
    {
        return $this->isFmoSuperAdmin();
    }

    /** Only the FMO Super Admin manages Facilities-side user accounts. */
    public function canManageFmoUsers(): bool
    {
        return $this->isFmoSuperAdmin();
    }

    /**
     * Whether this account may touch anything on the Asset Management /
     * OPEX administrative side. FMO accounts never can.
     */
    public function canAccessAssetManagement(): bool
    {
        // Explicit scope wins for all new accounts. This prevents Student
        // requestors (role=requestor, scope=fmo) from reaching Asset-only URLs
        // directly even though their role itself is not an FMO staff role.
        if ($this->access_scope === 'fmo') {
            return false;
        }
        if ($this->access_scope === 'asset') {
            return true;
        }

        // Legacy accounts keep the previous role-based behavior.
        return !$this->isFmoSide();
    }

    public function canHandleAssetScans(): bool
    {
        return $this->isAdmin() || $this->isHousekeeping();
    }

    /**
     * Backend-enforced scope for the FMO Super Admin's Users tab.
     *
     * Returns only accounts that belong to the Facilities side:
     *  - FMO Super Admin / FMO staff / housekeeping,
     *  - requestors from a facility-only department (restrict_supply_requests),
     *  - any requestor who has actually used Facilities Reservation or filed an
     *    Activity Proposal.
     *
     * Asset Management Super Admin, Asset Management Admins, approvers and
     * OPEX-only requestors are excluded at the query level, not in Blade.
     */
    public function scopeFacilitiesSide(Builder $query): Builder
    {
        return $query->where(function (Builder $outer) {
            $outer->whereIn('role', ['fmo_super_admin', 'fmo', 'housekeeping'])
                ->orWhere(function (Builder $inner) {
                    $inner->where('role', 'requestor')
                        ->where(function (Builder $scoped) {
                            // New accounts have explicit ownership. Only Student
                            // requestors belong to FMO User Management. Asset Management
                            // Requestors may still submit Activity Proposals, but
                            // Asset Management remains their account owner.
                            $scoped->where('account_type', 'student')
                                ->orWhere(function (Builder $legacy) {
                                    $legacy->whereNull('account_type')
                                        ->where(function (Builder $legacyScope) {
                                            $legacyScope->whereHas('department', fn (Builder $d) => $d->where('restrict_supply_requests', true))
                                                ->orWhereHas('facilityReservations')
                                                ->orWhereHas('activityProposals');
                                        });
                                });
                        });
                });
        });
    }

    /** Asset Management owns admins, approvers, Requestors and legacy non-FMO users. Housekeeping is managed by FMO. */
    public function scopeAssetManagementSide(Builder $query): Builder
    {
        return $query->whereNotIn('role', array_merge(self::FMO_ROLES, ['housekeeping']))
            ->where(function (Builder $outer) {
                $outer->where('role', '!=', 'requestor')
                    ->orWhereIn('account_type', ['requestor', 'staff', 'organization'])
                    ->orWhere(function (Builder $legacy) {
                        $legacy->whereNull('account_type')
                            ->where(function (Builder $legacyScope) {
                                $legacyScope->whereDoesntHave('department', fn (Builder $d) => $d->where('restrict_supply_requests', true));
                            });
                    });
            });
    }
}
