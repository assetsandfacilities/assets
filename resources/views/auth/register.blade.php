<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | NU Clark Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/nuclark-auth.css') }}?v={{ file_exists(public_path('css/nuclark-auth.css')) ? filemtime(public_path('css/nuclark-auth.css')) : '1' }}">
</head>
<body>
@php
    // Access level is no longer something the applicant picks — it is decided by
    // whether Asset Management issued them a voucher. Student registration is
    // simply "no voucher", so the old dropdown and summary field are gone.
    $isAsset      = (bool) $verifiedVoucher;
    $voucherType  = $verifiedVoucher['voucher_type'] ?? null;
    $approverType = $verifiedVoucher['approver_type'] ?? null;
    $accessTitle  = $isAsset
        ? ($voucherType === 'approver'
            ? 'Approver — '.ucwords(str_replace('_', ' ', (string) $approverType))
            : 'Requestor — Asset Management')
        : 'Student — Activity Proposals';
    $accessNote   = $isAsset
        ? ($voucherType === 'approver'
            ? 'Your approver type comes from the voucher and cannot be changed here. The account is active immediately after registration.'
            : 'This voucher-authorized requestor account can file charge slips, OPEX requisitions and activity proposals.')
        : 'Student accounts are activated after verified-email registration and are managed on the Facilities side.';
@endphp

<div class="shell">
    <aside class="rail">
        <div class="rail-brand">
            <div class="rail-brand-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
            <div>
                <div class="rail-brand-name">National University</div>
                <div class="rail-brand-sub">Clark — Asset Management</div>
            </div>
        </div>

        <h1>Create your account</h1>
        <p class="rail-lede">Kailangan munang ma-verify ang email bago makagawa ng account. Kung may voucher ka mula sa Asset Management, i-verify mo rin ito para ma-unlock ang Requestor o Approver access.</p>

        <div class="rail-steps">
            <div class="rail-step {{ $verifiedEmail ? 'done' : 'active' }}">
                <div class="rail-dot">{!! $verifiedEmail ? '&check;' : '1' !!}</div>
                <div class="rail-step-copy">
                    <strong>Email verification</strong>
                    <span>Send a code to your inbox, then enter the 6 digits.</span>
                </div>
            </div>
            <div class="rail-step {{ $isAsset ? 'done' : ($verifiedEmail ? 'active' : '') }}">
                <div class="rail-dot">{!! $isAsset ? '&check;' : '2' !!}</div>
                <div class="rail-step-copy">
                    <strong>Account access</strong>
                    <span>Voucher check for Requestor / Approver. Skip if student.</span>
                </div>
            </div>
            <div class="rail-step {{ $verifiedEmail ? 'active' : '' }}">
                <div class="rail-dot">3</div>
                <div class="rail-step-copy">
                    <strong>Account details</strong>
                    <span>Name, password and department to finish up.</span>
                </div>
            </div>
        </div>

        <div class="rail-foot">
            <a href="{{ route('login') }}" class="rail-back">&larr; Back to login</a>
        </div>
    </aside>

    <main class="pane">
        <div class="pane-head">
            <div>
                <div class="pane-eyebrow">Registration</div>
                <h2>Set up your access</h2>
                <p>Three short steps. Your access level is set automatically from your voucher.</p>
            </div>
            <div class="pane-progress">{{ $verifiedEmail ? ($isAsset ? 'Step 3 of 3' : 'Step 2 of 3') : 'Step 1 of 3' }}</div>
        </div>

        <div class="flow">
            {{-- ---------------- Step 1 ---------------- --}}
            <section class="flow-step {{ $verifiedEmail ? 'done' : 'active' }}">
                <div class="flow-marker">{!! $verifiedEmail ? '&check;' : '1' !!}</div>
                <div>
                    <div class="flow-title">
                        <h3>Verify your email</h3>
                        @if($verifiedEmail)<span class="tag tag-done">Verified</span>@endif
                    </div>
                    <p class="flow-sub">We send a 6-digit code to your inbox. The code expires in {{ $otpExpiryLabel }} and must be keyed in digit by digit.</p>

                    <div class="flow-card">
                        @if($verifiedEmail)
                            <div class="callout callout-ok">
                                <span class="dot"></span>
                                <div><strong>{{ $verifiedEmail }}</strong>This address is confirmed and will be used for your account.</div>
                            </div>
                        @else
                            {{-- One field to start with. The code row is only rendered
                                 once a code has actually been sent, so the step reads as
                                 a single action instead of two competing forms. --}}
                            <form method="POST" action="{{ route('register.send-code') }}">
                                @csrf
                                <div class="field-row">
                                    <div>
                                        <label class="form-label">Email address</label>
                                        <input type="email" name="email" class="form-control" value="{{ old('email', $pendingVerification['email'] ?? '') }}" placeholder="Enter your email" required>
                                    </div>
                                    <button class="btn-main {{ $pendingVerification ? 'btn-ghost' : '' }}" type="submit" @if($otpCooldown > 0) data-otp-cooldown="{{ $otpCooldown }}" @endif><span data-otp-cooldown-label>{{ $pendingVerification ? 'Resend code' : 'Send code' }}</span></button>
                                </div>
                            </form>

                            @if($pendingVerification && $otpResendsLeft !== null)
                                {{-- Panel revision: the resend allowance is finite, so the
                                     applicant is told how many are left instead of
                                     discovering the lock only after using them up. --}}
                                <div class="hint" data-otp-allowance>
                                    @if($otpResendsLeft > 0)
                                        {{ $otpResendsLeft }} of {{ $otpResendMax }} {{ \Illuminate\Support\Str::plural('resend', $otpResendMax) }} left for this address.
                                    @else
                                        You have used all {{ $otpResendMax }} resends for this address. Wait for the cooldown to finish before asking for another code.
                                    @endif
                                </div>
                            @endif

                            @if($pendingVerification)
                                <div class="code-reveal">
                                    <div class="code-sent">
                                        <span class="dot"></span>
                                        <div>Code sent to <strong>{{ $pendingVerification['email'] }}</strong>. Check your inbox, then enter it below.</div>
                                    </div>
                                    <form method="POST" action="{{ route('register.verify-code') }}">
                                        @csrf
                                        <div class="field-row">
                                            <div>
                                                <label class="form-label">Enter code</label>
                                                @include('partials.otp-input', ['otpId' => 'register-otp'])
                                            </div>
                                            <button class="btn-main" type="submit">Verify code</button>
                                        </div>
                                        <div class="hint">No need to retype your email — the code is matched to the address above.</div>
                                    </form>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </section>

            {{-- ---------------- Step 2 ---------------- --}}
            <section class="flow-step {{ $isAsset ? 'done' : ($verifiedEmail ? 'active' : '') }}">
                <div class="flow-marker">{!! $isAsset ? '&check;' : '2' !!}</div>
                <div>
                    <div class="flow-title">
                        <h3>Verify account access</h3>
                        @if($isAsset)
                            <span class="tag tag-done">Voucher verified</span>
                        @else
                            <span class="tag tag-optional">Skip this if you're a student</span>
                        @endif
                    </div>
                    <p class="flow-sub">Asset Management account verification.</p>

                    <div class="flow-card">
                        @if($isAsset)
                            <div class="access-chip" style="margin-bottom:0">
                                <div class="access-chip-icon">&#10003;</div>
                                <div class="access-chip-copy">
                                    <span>Authorized account</span>
                                    <strong>{{ $accessTitle }}</strong>
                                </div>
                            </div>
                            @if(!empty($verifiedVoucher['department_id']))
                                <div class="hint">Your department is locked to the one selected by Asset Management.</div>
                            @endif
                        @else
                            <div class="hint" style="margin-top:0;margin-bottom:13px">Requestor and Approver accounts require a valid voucher code issued by Asset Management.</div>
                            <form method="POST" action="{{ route('register.verify-voucher') }}">
                                @csrf
                                <label class="form-label">Voucher code</label>
                                <div class="voucher-row">
                                    <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" autocomplete="off" required>
                                    <button class="btn-main" type="submit">Verify Voucher</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ---------------- Step 3 ---------------- --}}
            <section class="flow-step {{ $verifiedEmail ? 'active' : '' }}">
                <div class="flow-marker">3</div>
                <div>
                    <div class="flow-title">
                        <h3>Finish account creation</h3>
                        @unless($verifiedEmail)<span class="tag tag-locked">Verify email first</span>@endunless
                    </div>
                    <p class="flow-sub">These details are printed on your requests and used to sign you in.</p>

                    <div class="flow-card">
                        <div class="access-chip">
                            <div class="access-chip-icon">{{ $isAsset ? ($voucherType === 'approver' ? 'A' : 'R') : 'S' }}</div>
                            <div class="access-chip-copy">
                                <span>Account access</span>
                                <strong>{{ $accessTitle }}</strong>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('register.submit') }}" id="registrationForm" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">
                                @if($isAsset)
                                {{-- Panel revision: voucher holders sign printed documents, so
                                     they pick the title that appears with their name. Students
                                     do not sign anything, so the field is not shown to them. --}}
                                <div class="col-md-3">
                                    <label class="form-label">Title</label>
                                    <select name="title" class="form-select">
                                        <option value="">None</option>
                                        @foreach(\App\Models\User::politeTitles() as $politeTitle)
                                            <option value="{{ $politeTitle }}" @selected(old('title') === $politeTitle)>{{ $politeTitle }}</option>
                                        @endforeach
                                    </select>
                                    <div class="hint">Printed with your name on approved forms.</div>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">Full name</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter your full name" required>
                                </div>
                                @else
                                <div class="col-md-6">
                                    <label class="form-label">Full name</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter your full name" required>
                                </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ $verifiedEmail ?? '' }}" placeholder="Verify your email above first" readonly required>
                                    <div class="hint">Carried over from Step 1 and cannot be edited here.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="8+ characters, 1 number, 1 symbol" data-pw-rules required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm password</label>
                                    <input type="password" name="password_confirmation" class="form-control" placeholder="Re-enter password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select name="department_id" class="form-select" {{ !empty($verifiedVoucher['department_id']) ? 'disabled' : '' }}>
                                        <option value="">Select your department</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}" @selected(old('department_id', $verifiedVoucher['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                    @if(!empty($verifiedVoucher['department_id']))
                                        <input type="hidden" name="department_id" value="{{ $verifiedVoucher['department_id'] }}">
                                        <div class="hint">Locked by your voucher.</div>
                                    @endif
                                </div>

                                @if($voucherType === 'approver')
                                <div class="col-12" id="approverSignatureSection">
                                    <div class="signature-block">
                                        <div class="sig-title">Required e-signature</div>
                                        <div class="hint" style="margin-top:0;margin-bottom:13px">This signature is locked after account creation and appears beside your printed name on approved proposals and receipts.</div>
                                        @include('partials.signature-input', ['signatureId' => 'registration-signature', 'signatureRequired' => true])
                                    </div>
                                </div>
                                @endif

                                <div class="col-12">
                                    <div class="callout callout-info" style="margin-bottom:14px">
                                        <span class="dot"></span>
                                        <div>{{ $accessNote }}</div>
                                    </div>
                                    <button class="btn-main btn-submit" type="submit">Create account</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

@include('auth.partials.password-toggle')
@include('partials.alerts')
</body>
</html>