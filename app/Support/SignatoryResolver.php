<?php

namespace App\Support;

use App\Models\User;

/**
 * Campus-wide signatories.
 *
 * NU Clark has exactly one Academic Director and one Executive Director for the
 * whole campus, and neither of them belongs to a department. Because of that
 * there is nothing for a requestor to choose: the routing is decided by the
 * role that the Super Admin assigned in Account Management, not by whoever is
 * filling out the form.
 *
 * Every module (Asset Management charge slips and FMO activity proposals) asks
 * this class instead of running its own query, so there is a single definition
 * of "who signs" and only one place to change if that ever becomes more than
 * one person per role.
 */
class SignatoryResolver
{
    /** Approver types that are campus-wide and therefore never department-bound. */
    public const CAMPUS_WIDE_TYPES = ['academic_director', 'executive'];

    /** Per-request memo so one page render does not repeat the same query. */
    protected static array $cache = [];

    public static function academicDirector(): ?User
    {
        return self::resolve('academic_director');
    }

    public static function executiveDirector(): ?User
    {
        return self::resolve('executive');
    }

    /**
     * The single active holder of a campus-wide approver role.
     *
     * Ordered by id so that, if a second account is ever created by hand
     * straight in the database, the system still behaves deterministically
     * instead of picking a different signatory on every request.
     */
    public static function resolve(string $approverType): ?User
    {
        if (array_key_exists($approverType, self::$cache)) {
            return self::$cache[$approverType];
        }

        return self::$cache[$approverType] = User::query()
            ->where('role', 'approver')
            ->where('approver_type', $approverType)
            ->where('is_approved', true)
            ->orderBy('id')
            ->first();
    }

    /** True when another active account already holds this campus-wide role. */
    public static function isTaken(string $approverType, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->where('role', 'approver')
            ->where('approver_type', $approverType)
            ->where('is_approved', true)
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();
    }

    public static function isCampusWide(?string $approverType): bool
    {
        return $approverType !== null && in_array($approverType, self::CAMPUS_WIDE_TYPES, true);
    }

    public static function label(string $approverType): string
    {
        return match ($approverType) {
            'academic_director' => 'Academic Director',
            'executive' => 'Executive Director',
            default => ucwords(str_replace('_', ' ', $approverType)),
        };
    }

    /**
     * Message shown when the role has no active holder yet. Submitting a form
     * in that state would create a document with nowhere to go, so the form is
     * blocked instead and the requestor is told who to contact.
     */
    public static function missingMessage(string $approverType): string
    {
        return 'No active '.self::label($approverType).' is configured in the system, '
            .'so this cannot be submitted yet. Please contact the Super Admin.';
    }

    /**
     | Department-bound signatory (panel revision).
     |
     | The requestor no longer picks the person who reviews their own request.
     | The route is derived from the department their account is assigned to,
     | so a Program Chair / Dean is looked up rather than chosen. Ordered by id
     | for the same determinism as the campus-wide lookup above.
     */
    public static function forDepartment(?int $departmentId, string $approverType): ?User
    {
        if (!$departmentId) {
            return null;
        }

        $key = 'dept:'.$departmentId.':'.$approverType;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return self::$cache[$key] = User::query()
            ->where('role', 'approver')
            ->where('approver_type', $approverType)
            ->where('department_id', $departmentId)
            ->where('is_approved', true)
            ->orderBy('id')
            ->first();
    }

    /** Message when a department has no approver of this type configured yet. */
    public static function missingDepartmentMessage(string $approverType, ?string $departmentName = null): string
    {
        return 'No active '.self::label($approverType).' is assigned to '
            .($departmentName ? 'the '.$departmentName.' department' : 'your department')
            .', so this cannot be submitted yet. Please contact the Super Admin.';
    }

    /** Only for tests / long-running processes. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
