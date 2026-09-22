<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccessVoucher;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Single source of truth for the account password policy:
     * at least 8 characters, at least one number, and at least one symbol.
     * Used by registration, password reset, and the two user-management
     * controllers, so the rule can never drift apart between screens.
     */
    public static function passwordRules(): Password
    {
        return Password::min(8)->numbers()->symbols();
    }

    private const REGISTRATION_CODE_KEY = 'registration_code';
    private const REGISTRATION_VERIFIED_KEY = 'registration_verified_email';
    private const REGISTRATION_VOUCHER_KEY = 'registration_asset_voucher';
    private const RESET_CODE_KEY = 'password_reset_code';
    private const RESET_VERIFIED_KEY = 'password_reset_verified_email';


    /* ----------------------------------------------------------------------
     | Institutional email + OTP throttling
     |
     | An OTP only proves that somebody can open that inbox. What actually
     | proves NU affiliation is the domain, because only the university can
     | issue an address on it. Registration is therefore restricted to the
     | student domain, with the staff domains accepted only for applicants
     | who have already verified an Asset Management voucher (approvers and
     | authorized requestors are not students). Both lists live in config/nu.php.
     * -------------------------------------------------------------------- */

    public static function studentDomain(): string
    {
        return strtolower(trim((string) config('nu.student_domain', 'students.nu-clark.edu.ph')));
    }

    /** Domains accepted for this applicant, given whether a voucher is verified. */
    public static function allowedEmailDomains(bool $withVoucher = false): array
    {
        $domains = [self::studentDomain()];

        if ($withVoucher) {
            foreach ((array) config('nu.staff_domains', []) as $domain) {
                $domain = strtolower(trim((string) $domain));
                if ($domain !== '') {
                    $domains[] = $domain;
                }
            }
        }

        return array_values(array_unique($domains));
    }

    private static function emailDomainOf(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : strtolower(substr($email, $at + 1));
    }

    /** True when the address belongs to one of the allowed institutional domains. */
    public static function isInstitutionalEmail(string $email, bool $withVoucher = false): bool
    {
        return in_array(self::emailDomainOf($email), self::allowedEmailDomains($withVoucher), true);
    }

    private static function institutionalEmailMessage(bool $withVoucher = false): string
    {
        $domains = self::allowedEmailDomains($withVoucher);
        $list = count($domains) === 1
            ? '@'.$domains[0]
            : '@'.implode(' or @', $domains);

        return 'Use your NU Clark institutional email ('.$list.'). Personal addresses such as Gmail or Yahoo are not accepted.';
    }

    /** Rate-limiter key for one address and one purpose (register / reset). */
    private static function otpEmailKey(string $purpose, string $email): string
    {
        return 'otp-email:'.$purpose.':'.strtolower(trim($email));
    }

    /** Total sends allowed per address: the first code plus the resends. */
    private static function otpSendAllowance(): int
    {
        return max(1, (int) config('nu.otp.resend_max', 3)) + 1;
    }

    /**
     * Resends still available for this address, for display on the form.
     * Null means the address has never asked for a code in this window.
     */
    public static function resendsLeft(string $purpose, ?string $email): ?int
    {
        if (!$email) {
            return null;
        }

        $used = RateLimiter::attempts(self::otpEmailKey($purpose, $email));
        if ($used < 1) {
            return null;
        }

        // The first send is not a resend, so the allowance for resends is one
        // less than the total number of sends permitted.
        return max(0, self::otpSendAllowance() - $used);
    }

    /**
     * Guards one OTP send: a per-IP cap, the per-address resend cap, and the
     * short cooldown between consecutive codes for the same address. Returns
     * an error string, or null when the request may proceed.
     */
    private function otpThrottleError(Request $request, string $email, string $purpose, ?array $pending): ?string
    {
        $cooldown = (int) config('nu.otp.cooldown_seconds', 60);

        if ($pending
            && strcasecmp((string) ($pending['email'] ?? ''), $email) === 0
            && !empty($pending['sent_at'])) {
            $elapsed = now()->timestamp - (int) $pending['sent_at'];
            if ($elapsed < $cooldown) {
                $wait = $cooldown - $elapsed;

                return 'Please wait '.$wait.' more '.\Illuminate\Support\Str::plural('second', $wait).' before requesting another code.';
            }
        }

        $ipKey = 'otp-ip:'.$purpose.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, (int) config('nu.otp.ip_max_attempts', 30))) {
            return 'Too many verification codes requested from this connection. Try again in '
                .ceil(RateLimiter::availableIn($ipKey) / 60).' minute(s).';
        }

        /*
         | Per-address resend cap. The window is the lock itself: once the first
         | code plus the allowed resends have gone out, nothing more is sent to
         | that address until the window expires. This is what actually protects
         | the mail quota, because one address is what a script hammers.
         */
        $lockMinutes = max(1, (int) config('nu.otp.resend_lock_minutes', 50));
        $emailKey = self::otpEmailKey($purpose, $email);
        if (RateLimiter::tooManyAttempts($emailKey, self::otpSendAllowance())) {
            $wait = max(1, (int) ceil(RateLimiter::availableIn($emailKey) / 60));

            return 'You have used all '.(int) config('nu.otp.resend_max', 3).' resends for this email address. '
                .'Please try again in '.$wait.' '.\Illuminate\Support\Str::plural('minute', $wait).'.';
        }

        RateLimiter::hit($ipKey, (int) config('nu.otp.ip_decay_minutes', 15) * 60);
        RateLimiter::hit($emailKey, $lockMinutes * 60);

        return null;
    }

    /** Seconds left before another code may be requested for the pending address. */
    public static function cooldownRemaining(?array $pending): int
    {
        if (!$pending || empty($pending['sent_at'])) {
            return 0;
        }

        $left = (int) config('nu.otp.cooldown_seconds', 60) - (now()->timestamp - (int) $pending['sent_at']);

        return max(0, $left);
    }

    /**
     * Seconds a code stays usable. Kept in seconds because the window is now
     * shorter than a minute's worth of rounding error would allow.
     */
    private static function otpExpirySeconds(): int
    {
        return max(30, (int) round((float) config('nu.otp.expiry_minutes', 2) * 60));
    }

    /** Human wording for the expiry, e.g. "2 minutes". */
    public static function otpExpiryLabel(): string
    {
        $seconds = self::otpExpirySeconds();
        if ($seconds % 60 === 0) {
            $minutes = intdiv($seconds, 60);

            return $minutes.' '.\Illuminate\Support\Str::plural('minute', $minutes);
        }

        return $seconds.' seconds';
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister(Request $request)
    {
        return view('auth.register', [
            'verifiedEmail' => $request->session()->get(self::REGISTRATION_VERIFIED_KEY),
            'pendingVerification' => $request->session()->get(self::REGISTRATION_CODE_KEY),
            'departments' => Department::orderBy('name')->get(),
            'verifiedVoucher' => $request->session()->get(self::REGISTRATION_VOUCHER_KEY),
            'otpCooldown' => self::cooldownRemaining($request->session()->get(self::REGISTRATION_CODE_KEY)),
            'otpExpiryLabel' => self::otpExpiryLabel(),
            'otpResendMax' => (int) config('nu.otp.resend_max', 3),
            'otpResendsLeft' => self::resendsLeft('register', $request->session()->get(self::REGISTRATION_CODE_KEY)['email'] ?? null),
        ]);
    }

    public function sendVerificationCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        // Domain check runs before anything is generated or sent, so a personal
        // address never consumes a code or a slice of the mail quota.
        $hasVoucher = !empty($request->session()->get(self::REGISTRATION_VOUCHER_KEY)['voucher_id']);
        if (!self::isInstitutionalEmail($validated['email'], $hasVoucher)) {
            return back()
                ->withErrors(['email' => self::institutionalEmailMessage($hasVoucher)])
                ->withInput(['email' => $validated['email']]);
        }

        $throttleError = $this->otpThrottleError(
            $request,
            $validated['email'],
            'register',
            $request->session()->get(self::REGISTRATION_CODE_KEY)
        );
        if ($throttleError) {
            return back()->withErrors(['email' => $throttleError])->withInput(['email' => $validated['email']]);
        }

        $code = (string) random_int(100000, 999999);

        $request->session()->put(self::REGISTRATION_CODE_KEY, [
            'email' => $validated['email'],
            'code_hash' => Hash::make($code),
            'sent_at' => now()->timestamp,
            'expires_at' => now()->addSeconds(self::otpExpirySeconds())->timestamp,
        ]);

        $request->session()->forget(self::REGISTRATION_VERIFIED_KEY);

        // ✅ FIXED EMAIL SENDING WITH ERROR HANDLING
        try {
            Mail::raw(
"Your verification code is: {$code}\n\nThis code will expire in ".self::otpExpiryLabel().".",
                function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('Your Account Verification Code');
                }
            );
        } catch (\Throwable $e) {
            \Log::error('MAIL ERROR: '.$e->getMessage());

            // TEMPORARY: showing the real exception message on-screen for debugging,
            // since Railway's log viewer doesn't show file-based Laravel logs.
            // Remove the $e->getMessage() part once mail sending is confirmed working.
            return back()->with('error', 'Email failed ('.$e->getMessage().'). Your code is: '.$code)
                ->withInput([
                    'email' => $validated['email'],
                ]);
        }

        return back()->with('success', 'Verification code sent to your email.')->withInput([
            'email' => $validated['email'],
        ]);
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $verification = $request->session()->get(self::REGISTRATION_CODE_KEY);

        if (!$verification || empty($verification['email'])) {
            return back()->withErrors(['code' => 'Please enter your email and send a verification code first.'])->withInput();
        }

        if (now()->timestamp > ($verification['expires_at'] ?? 0)) {
            $request->session()->forget(self::REGISTRATION_CODE_KEY);
            return back()->withErrors(['code' => 'Verification code expired. Please request a new code.'])->withInput();
        }

        if (!Hash::check($validated['code'], $verification['code_hash'] ?? '')) {
            return back()->withErrors(['code' => 'Invalid verification code.'])->withInput();
        }

        $request->session()->put(self::REGISTRATION_VERIFIED_KEY, $verification['email']);

        return back()->with('success', 'Email verified. You can now create your account.')->withInput([
            'verified_email' => $verification['email'],
        ]);
    }


    public function verifyVoucher(Request $request)
    {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string', 'max:64'],
        ]);

        $voucher = AccessVoucher::query()
            ->where('code_hash', AccessVoucher::hashCode($validated['voucher_code']))
            ->first();

        if (!$voucher || !$voucher->isUsable()) {
            $request->session()->forget(self::REGISTRATION_VOUCHER_KEY);
            return back()->withErrors(['voucher_code' => 'Invalid, expired, used, or revoked voucher code. Please request a new voucher from Asset Management.'])->withInput();
        }

        $request->session()->put(self::REGISTRATION_VOUCHER_KEY, [
            'voucher_id' => $voucher->id,
            'voucher_type' => $voucher->voucher_type,
            'approver_type' => $voucher->approver_type,
            'department_id' => $voucher->department_id,
        ]);

        return back()->with('success', 'Asset Management voucher verified. Your authorized account form is now unlocked.')->withInput();
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Polite title shown with the name on printed forms. Restricted to
            // the configured list so nothing arbitrary reaches a document.
            'title' => ['nullable', 'string', \Illuminate\Validation\Rule::in(User::politeTitles())],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', self::passwordRules()],
            'department_id' => ['nullable', 'exists:departments,id'],
            'signature_file' => ['nullable', 'file', 'max:2048'],
            'signature_drawn' => ['nullable', 'string'],
        ]);

        $verifiedEmail = $request->session()->get(self::REGISTRATION_VERIFIED_KEY);
        if ($verifiedEmail !== $validated['email']) {
            return back()->withErrors(['email' => 'Please verify this email address first.'])->withInput();
        }

        $voucherSession = $request->session()->get(self::REGISTRATION_VOUCHER_KEY);

        // Second enforcement point: the domain is checked again on submit, so a
        // crafted POST cannot skip the check done at send-code time.
        if (!self::isInstitutionalEmail($validated['email'], !empty($voucherSession['voucher_id']))) {
            return back()
                ->withErrors(['email' => self::institutionalEmailMessage(!empty($voucherSession['voucher_id']))])
                ->withInput();
        }

        // The applicant no longer chooses their access level on the form. It is
        // derived here: a verified single-use voucher in the session means an
        // Asset Management account, anything else is a student account.
        $validated['account_access'] = !empty($voucherSession['voucher_id']) ? 'asset' : 'student';

        $signatureData = null;
        if (($validated['account_access'] ?? null) === 'asset' && (($voucherSession['voucher_type'] ?? null) === 'approver')) {
            $signatureData = \App\Support\SignatureData::fromRequest($request, true);
        }

        try {
            $user = DB::transaction(function () use ($validated, $voucherSession, $signatureData) {
                $role = 'requestor';
                $accountType = 'student';
                $accessScope = 'fmo';
                $approverType = null;
                $departmentId = $validated['department_id'] ?? null;
                $isAutoApproved = true;
                $voucher = null;

                if ($validated['account_access'] === 'asset') {
                    if (!$voucherSession || empty($voucherSession['voucher_id'])) {
                        throw new \RuntimeException('A verified Asset Management voucher is required.');
                    }

                    $voucher = AccessVoucher::query()->lockForUpdate()->find($voucherSession['voucher_id']);
                    if (!$voucher || !$voucher->isUsable()) {
                        throw new \RuntimeException('This voucher is no longer valid. Please request and verify a new voucher.');
                    }

                    $accountType = $voucher->voucher_type;
                    $accessScope = 'asset';
                    if ($voucher->voucher_type === 'approver') {
                        $role = 'approver';
                        $approverType = $voucher->approver_type;
                        $accountType = 'approver';
                    } elseif ($voucher->voucher_type === 'requestor') {
                        $role = 'requestor';
                        $accountType = 'requestor';
                    } else {
                        throw new \RuntimeException('Unsupported voucher type.');
                    }

                    // If Asset Management bound the voucher to a department, the
                    // registration form cannot override it.
                    if ($voucher->department_id) {
                        $departmentId = $voucher->department_id;
                    }
                }

                $user = User::create([
                    'name' => $validated['name'],
                    // Only voucher accounts sign documents, so only they carry a
                    // title; a student posting one anyway gets it ignored.
                    'title' => $voucher ? ($validated['title'] ?? null) : null,
                    'email' => $validated['email'],
                    'password' => Hash::driver('bcrypt')->make($validated['password'], [
                        'rounds' => (int) config('security.bcrypt_rounds', 12),
                    ]),
                    'department_id' => $departmentId,
                    'role' => $role,
                    'account_type' => $accountType,
                    'access_scope' => $accessScope,
                    'approver_type' => $approverType,
                    'is_approved' => $isAutoApproved,
                    'approved_at' => $isAutoApproved ? now() : null,
                    'email_verified_at' => now(),
                    'signature_data' => $signatureData,
                    'signature_updated_at' => $signatureData ? now() : null,
                ]);

                if ($voucher) {
                    $voucher->update(['used_at' => now(), 'used_by' => $user->id]);
                }

                return $user;
            });
        } catch (\RuntimeException $e) {
            $request->session()->forget(self::REGISTRATION_VOUCHER_KEY);
            return back()->withErrors(['voucher_code' => $e->getMessage()])->withInput();
        }

        // No second Asset Management approval is required here. The single-use
        // voucher was already generated by Asset Management, so successful
        // voucher registration produces an active account immediately.

        $request->session()->forget([
            self::REGISTRATION_CODE_KEY,
            self::REGISTRATION_VERIFIED_KEY,
            self::REGISTRATION_VOUCHER_KEY,
        ]);

        return redirect()->route('login')->with('success', 'Account created and verified. You can log in now.');
    }

    public function showForgotPassword(Request $request)
    {
        return view('auth.forgot-password', [
            'verifiedEmail' => $request->session()->get(self::RESET_VERIFIED_KEY),
            'pendingVerification' => $request->session()->get(self::RESET_CODE_KEY),
            'otpCooldown' => self::cooldownRemaining($request->session()->get(self::RESET_CODE_KEY)),
            'otpExpiryLabel' => self::otpExpiryLabel(),
            'otpResendMax' => (int) config('nu.otp.resend_max', 3),
            'otpResendsLeft' => self::resendsLeft('reset', $request->session()->get(self::RESET_CODE_KEY)['email'] ?? null),
        ]);
    }

    public function sendResetCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $throttleError = $this->otpThrottleError(
            $request,
            $validated['email'],
            'reset',
            $request->session()->get(self::RESET_CODE_KEY)
        );
        if ($throttleError) {
            return back()->withErrors(['email' => $throttleError])->withInput(['email' => $validated['email']]);
        }

        $user = User::where('email', $validated['email'])->first();
        $code = (string) random_int(100000, 999999);

        // Only a registered email actually gets a code stored/sent — but the
        // response message is identical either way, so this screen can't be
        // used to fish for which emails have accounts.
        if ($user) {
            $request->session()->put(self::RESET_CODE_KEY, [
                'email' => $validated['email'],
                'code_hash' => Hash::make($code),
                'sent_at' => now()->timestamp,
                'expires_at' => now()->addSeconds(self::otpExpirySeconds())->timestamp,
            ]);

            try {
                Mail::raw(
                    "Your password reset code is: {$code}\n\nThis code will expire in ".self::otpExpiryLabel().". If you did not request a password reset, you can safely ignore this email.",
                    function ($message) use ($validated) {
                        $message->to($validated['email'])
                            ->subject('Your Password Reset Code');
                    }
                );
            } catch (\Throwable $e) {
                \Log::error('MAIL ERROR: '.$e->getMessage());

                // Same "temporary debugging" trade-off used in sendVerificationCode()
                // above — Railway's log viewer doesn't surface file-based Laravel
                // logs, so show the code on-screen if mail fails instead of leaving
                // the admin stuck. Remove once mail sending is confirmed working.
                return back()->with('error', 'Email failed ('.$e->getMessage().'). Your code is: '.$code)
                    ->withInput(['email' => $validated['email']]);
            }
        }

        $request->session()->forget(self::RESET_VERIFIED_KEY);

        return back()->with('success', 'If that email has an account, a reset code has been sent to it.')->withInput([
            'email' => $validated['email'],
        ]);
    }

    public function verifyResetCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $verification = $request->session()->get(self::RESET_CODE_KEY);

        if (!$verification || ($verification['email'] ?? null) !== $validated['email']) {
            return back()->withErrors(['email' => 'No reset request found for this email. Please send a code first.'])->withInput();
        }

        if (now()->timestamp > ($verification['expires_at'] ?? 0)) {
            $request->session()->forget(self::RESET_CODE_KEY);
            return back()->withErrors(['code' => 'Reset code expired. Please request a new code.'])->withInput();
        }

        if (!Hash::check($validated['code'], $verification['code_hash'] ?? '')) {
            return back()->withErrors(['code' => 'Invalid reset code.'])->withInput();
        }

        $request->session()->put(self::RESET_VERIFIED_KEY, $validated['email']);

        return back()->with('success', 'Code verified. You can now set a new password.')->withInput([
            'verified_email' => $verification['email'],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', self::passwordRules()],
        ]);

        $verifiedEmail = $request->session()->get(self::RESET_VERIFIED_KEY);

        if ($verifiedEmail !== $validated['email']) {
            return back()->withErrors(['email' => 'Please verify this email with a reset code first.'])->withInput();
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            $request->session()->forget([self::RESET_CODE_KEY, self::RESET_VERIFIED_KEY]);
            return back()->withErrors(['email' => 'Account not found.'])->withInput();
        }

        $user->forceFill([
            'password' => Hash::driver('bcrypt')->make($validated['password'], [
                'rounds' => (int) config('security.bcrypt_rounds', 12),
            ]),
        ])->save();

        $request->session()->forget([self::RESET_CODE_KEY, self::RESET_VERIFIED_KEY]);

        return redirect()->route('login')->with('success', 'Password reset successful. You can now log in with your new password.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user && !$user->is_approved) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors(['email' => 'Your account is still pending admin approval.'])->onlyInput('email');
            }

            if ($user && Hash::needsRehash($user->password)) {
                $user->forceFill([
                    'password' => Hash::driver('bcrypt')->make($credentials['password'], [
                        'rounds' => (int) config('security.bcrypt_rounds', 12),
                    ]),
                ])->save();
            }

            return redirect()->route($user->homeRouteName())->with('success', 'Welcome back.');
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}