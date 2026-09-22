ACCOUNT ACCESS UPDATE — SEPTEMBER 6, 2026

WHAT CHANGED
1. Student registration: no voucher, active after successful verified-email registration.
2. Asset Management Staff: valid Staff voucher -> account created -> ACTIVE immediately.
3. Asset Management Organization: valid Organization voucher -> account created -> ACTIVE immediately.
4. Asset Management Approver: valid Approver voucher -> approver type comes from voucher -> ACTIVE immediately.
5. There is NO second Asset Management admin approval after voucher registration.
6. Account Type no longer displays "Legacy". The Users page displays the actual/resolved account type.

IMPORTANT AFTER UPLOAD
Run:
    php artisan optimize:clear
    php artisan migrate --force

The new migration also activates already-created Requestor/Approver accounts that were left pending by the previous version.

CURRENT ASSET ACCESS TYPES: Requestor and Approver only. Asset Management Super Admin can permanently delete Voucher History records.
