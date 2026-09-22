<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | NU Clark Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/nuclark-auth.css') }}?v={{ file_exists(public_path('css/nuclark-auth.css')) ? filemtime(public_path('css/nuclark-auth.css')) : '1' }}">
</head>
<body>
<div class="card-wrap">
    <div class="row g-0">
        <div class="col-lg-4">
            <div class="left-pane">
                <div class="auth-brand">
                    <div class="auth-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
                    <div>
                        <div class="auth-brand-name">NU Clark</div>
                        <div class="auth-brand-sub">Account recovery</div>
                    </div>
                </div>
                <h1>Reset your password</h1>
                <p>Para sa seguridad, kailangan munang ma-verify ang access mo sa email bago makapagtakda ng bagong password.</p>
                <div class="step"><strong>Step 1</strong>Enter your registered email and send a reset code.</div>
                <div class="step"><strong>Step 2</strong>Type the 6-digit code from your email.</div>
                <div class="step"><strong>Step 3</strong>Set your new password.</div>
                <div class="mt-4"><a href="{{ route('login') }}" class="btn btn-light btn-sm">Back to login</a></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="right-pane">
                <div class="pane-head" style="margin-bottom:36px">
                    <div>
                        <div class="pane-eyebrow">Password reset</div>
                        <h2>Verify, then set a new password</h2>
                        <p>Three steps. The reset form unlocks once your code is verified.</p>
                    </div>
                </div>
                <div class="box">
                    <h5>1. Send reset code</h5>
                    <form method="POST" action="{{ route('password.send-code') }}">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Registered email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', old('verified_email', $verifiedEmail ?? '')) }}" placeholder="Enter your account email" required>
                            </div>
                            <div class="col-md-4">
                                <button class="btn-main w-100" type="submit" @if($otpCooldown > 0) data-otp-cooldown="{{ $otpCooldown }}" @endif><span data-otp-cooldown-label>Send code</span></button>
                            </div>
                        </div>
                        <div class="muted mt-2">
                            Code expires in {{ $otpExpiryLabel }}. Enter it digit by digit — pasting is disabled.
                            @if($otpResendsLeft !== null)
                                @if($otpResendsLeft > 0)
                                    <strong>{{ $otpResendsLeft }} of {{ $otpResendMax }} {{ \Illuminate\Support\Str::plural('resend', $otpResendMax) }} left.</strong>
                                @else
                                    <strong>All {{ $otpResendMax }} resends used — wait for the cooldown before asking again.</strong>
                                @endif
                            @endif
                        </div>
                    </form>
                </div>

                <div class="box">
                    <h5>2. Verify reset code</h5>
                    @if($verifiedEmail)
                        <div class="mb-3"><span class="verified-badge">Verified: {{ $verifiedEmail }}</span></div>
                    @endif
                    <form method="POST" action="{{ route('password.verify-code') }}">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', old('verified_email', $verifiedEmail ?? ($pendingVerification['email'] ?? ''))) }}" placeholder="Same email as above" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">6-digit code</label>
                                @include('partials.otp-input', ['otpId' => 'reset-otp'])
                            </div>
                            <div class="col-md-3">
                                <button class="btn-main w-100" type="submit">Verify code</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="box mb-0">
                    <h5>3. Set new password</h5>
                    <form method="POST" action="{{ route('password.reset') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Verified email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('verified_email', $verifiedEmail ?? '') }}" placeholder="Verify your email above first" required @if(!$verifiedEmail) readonly @endif>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New password</label>
                                <input type="password" name="password" class="form-control" placeholder="8+ characters, 1 number, 1 symbol" data-pw-rules required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm new password</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Re-enter new password" required>
                            </div>
                            <div class="col-12">
                                <div class="muted mb-2">You can only set a new password after verifying the code sent to your email above.</div>
                                <button class="btn-main" type="submit" @if(!$verifiedEmail) disabled @endif>Reset password</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@include('auth.partials.password-toggle')
@include('partials.alerts')
</body>
</html>
