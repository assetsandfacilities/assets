<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | NU Clark Asset & Facilities Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/nuclark-auth.css') }}?v={{ file_exists(public_path('css/nuclark-auth.css')) ? filemtime(public_path('css/nuclark-auth.css')) : '1' }}">
</head>
<body class="login-page">
<div class="login-shell">
    <aside class="login-hero" aria-label="NU Clark platform overview">
        <div class="login-hero-grid" aria-hidden="true"></div>
        <div class="login-hero-orb login-hero-orb-one" aria-hidden="true"></div>
        <div class="login-hero-orb login-hero-orb-two" aria-hidden="true"></div>

        <div class="login-hero-content">
            <a class="login-brand" href="{{ route('login') }}" aria-label="NU Clark home">
                <span class="login-brand-mark">
                    <img src="{{ asset('images/nu-logo.png') }}" alt="National University logo">
                </span>
                <span class="login-brand-copy">
                    <strong>NU Clark</strong>
                    <small>National University</small>
                </span>
            </a>

            <div class="login-hero-copy">
                <span class="login-hero-kicker">Integrated Campus Operations</span>
                <h1>Assets &amp; Facilities Management System</h1>
                <p>One secure workspace for campus assets, requisitions, inventory, activity proposals and facility reservations.</p>

                <div class="login-feature-list" aria-label="Platform features">
                    <div class="login-feature-item">
                        <span class="login-feature-icon"><i class="bi bi-box-seam"></i></span>
                        <span>
                            <strong>Asset &amp; Inventory</strong>
                            <small>Track equipment, stock and issuances.</small>
                        </span>
                    </div>
                    <div class="login-feature-item">
                        <span class="login-feature-icon"><i class="bi bi-file-earmark-text"></i></span>
                        <span>
                            <strong>CAPEX &amp; OPEX Requests</strong>
                            <small>Manage requisitions and approval workflows.</small>
                        </span>
                    </div>
                    <div class="login-feature-item">
                        <span class="login-feature-icon"><i class="bi bi-building-check"></i></span>
                        <span>
                            <strong>Facility Reservations</strong>
                            <small>Coordinate venues, schedules and proposals.</small>
                        </span>
                    </div>
                </div>
            </div>

            <div class="login-hero-footer">
                <span>National University – Clark</span>
                <span class="login-hero-footer-dot" aria-hidden="true"></span>
                <span>Education that works.</span>
            </div>
        </div>
    </aside>

    <main class="login-main">
        <div class="login-main-decoration login-main-decoration-one" aria-hidden="true"></div>
        <div class="login-main-decoration login-main-decoration-two" aria-hidden="true"></div>

        <section class="login-card" aria-labelledby="loginTitle">
            <div class="login-card-head">
                <div class="login-mobile-brand" aria-hidden="true">
                    <img src="{{ asset('images/nu-logo.png') }}" alt="">
                    <span><strong>NU Clark</strong><small>Asset &amp; Facilities Management</small></span>
                </div>

                <div class="login-security-chip">
                    <i class="bi bi-shield-check"></i>
                    <span>Secure account access</span>
                </div>
                <h2 id="loginTitle">Welcome back</h2>
                <p>Sign in using the email address connected to your NU Clark account.</p>
            </div>

            <form method="POST" action="{{ route('login.submit') }}" class="login-form" novalidate>
                @csrf

                <div class="login-field">
                    <label class="login-label" for="loginEmail">Email address</label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                        <input
                            type="email"
                            name="email"
                            id="loginEmail"
                            class="form-control login-input"
                            value="{{ old('email') }}"
                            placeholder="you@nuclark.local"
                            autocomplete="email"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="login-field">
                    <div class="login-label-row">
                        <label class="login-label" for="loginPassword">Password</label>
                        <a href="{{ route('password.request') }}" class="login-forgot">Forgot password?</a>
                    </div>
                    <div class="login-input-wrap login-password-field">
                        <span class="login-input-icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                        <input
                            type="password"
                            name="password"
                            id="loginPassword"
                            class="form-control login-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                    </div>
                </div>

                <button class="login-submit" type="submit">
                    <span>Sign in</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="login-divider" aria-hidden="true"><span>New to the system?</span></div>

            <a href="{{ route('register') }}" class="login-register">
                <span class="login-register-icon"><i class="bi bi-person-plus"></i></span>
                <span class="login-register-copy">
                    <strong>Create an account</strong>
                    <small>Register with your verified email or issued access voucher.</small>
                </span>
                <i class="bi bi-chevron-right login-register-arrow" aria-hidden="true"></i>
            </a>

            <div class="login-help-note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>Use only the account assigned or verified for your NU Clark access.</span>
            </div>
        </section>

        <div class="login-main-footer">NU Clark Integrated Assets &amp; Facilities Platform</div>
    </main>
</div>
@include('auth.partials.password-toggle')
@include('partials.alerts')
</body>
</html>
