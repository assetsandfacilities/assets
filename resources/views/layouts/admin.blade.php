<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NU Clark Asset Management' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/nuclark-modernist.css') }}?v={{ file_exists(public_path('css/nuclark-modernist.css')) ? filemtime(public_path('css/nuclark-modernist.css')) : '1' }}">
    @stack('styles')
</head>
<body>
@php
    $navUser = auth()->user();
    $navFmoSuper = $navUser->isFmoSuperAdmin();
    $navFmoStaff = $navUser->isFmo();
    $navFmoSide = $navUser->isFmoSide();
    $navAll = $navUser->isSuperAdmin();
    $navAssetAdmin = $navUser->isAssetManagementAdmin();
    $navRequestor = $navUser->isRequestor();
    $navSupplyRequestor = $navUser->canRequestSupplies();
    $navHousekeeping = $navUser->isHousekeeping();
    $navDean = $navUser->isDeanApprover();
    $navExecutive = $navUser->isExecutiveApprover();
    $navProposalSigner = $navUser->isAdviserApprover() || $navUser->isSdaoApprover() || $navUser->isAcademicDirectorApprover() || $navDean || $navExecutive;

    /* Navigation is built as grouped data so the rail can show section
       labels and running index codes. Every link keeps the exact role
       condition and active-state rule it had before the redesign. */
    $navGroups = [];
    $navItem = fn ($href, $active, $name, $icon) => ['href' => $href, 'active' => $active, 'name' => $name, 'icon' => $icon];

    if ($navFmoSide) {
        /* FACILITIES (FMO) NAVIGATION — shown only to the FMO Super Admin and
           FMO staff. No Asset Management / OPEX links and no Activity
           Proposals tab (the FMO sees that information through Reservation
           Requests -> View All Details). */
        $navGroups[] = ['label' => 'Overview', 'items' => [
            $navItem(route('fmo.dashboard'), request()->routeIs('fmo.dashboard'), 'Dashboard', 'bi-speedometer2'),
        ]];
        $navGroups[] = ['label' => 'Bookings', 'items' => [
            $navItem(route('fmo.reservations.index'), request()->routeIs('fmo.reservations.*'), 'Reservations', 'bi-calendar-check'),
        ]];
        $navGroups[] = ['label' => 'Catalog', 'items' => [
            $navItem(route('fmo.venues.index'), request()->routeIs('fmo.venues.*'), 'Venues', 'bi-building'),
            $navItem(route('fmo.items.index'), request()->routeIs('fmo.items.*'), 'Facility Items', 'bi-box-seam'),
            $navItem(route('fmo.services.index'), request()->routeIs('fmo.services.*'), 'Services', 'bi-tools'),
        ]];
        $fmoAdmin = [
            /* Printable registration QR for the office door (panel revision). */
            $navItem(route('fmo.registration-qr'), request()->routeIs('fmo.registration-qr'), 'Registration QR', 'bi-qr-code'),
        ];
        if ($navFmoSuper) {
            $fmoAdmin[] = $navItem(route('fmo.users.index'), request()->routeIs('fmo.users.*'), 'FMO Users', 'bi-people');
        }
        $navGroups[] = ['label' => 'Administration', 'items' => $fmoAdmin];
    } else {
        /* ASSET MANAGEMENT NAVIGATION — never rendered for FMO accounts. */
        $g = [];
        if ($navAll || $navAssetAdmin) {
            $g['Overview'][] = $navItem(route('dashboard'), request()->routeIs('dashboard'), 'Dashboard', 'bi-grid');
            $g['Inventory'][] = $navItem(route('items.index', ['type' => 'CAPEX']), request()->routeIs('items.*') && request('type', 'CAPEX') === 'CAPEX', 'Capex', 'bi-pc-display');
        }
        if ($navAll || $navAssetAdmin || $navSupplyRequestor) {
            $g['Inventory'][] = $navItem(route('items.index', ['type' => 'OPEX']), request()->routeIs('items.*') && request('type') === 'OPEX', 'Opex', 'bi-layers');
        }
        if ($navAll || $navAssetAdmin || $navSupplyRequestor || $navDean || $navExecutive) {
            $g['Requests'][] = $navItem(route('requisitions.index'), request()->routeIs('requisitions.*'), 'Requisitions', 'bi-file-earmark-text');
        }
        if ($navRequestor || $navProposalSigner) {
            $g['Requests'][] = $navItem(route('activity-proposals.index'), request()->routeIs('activity-proposals.*'), 'Activity Proposals', 'bi-file-earmark-check');
        }
        if ($navAll || $navAssetAdmin || $navHousekeeping) {
            $g['Operations'][] = $navItem(route('asset-scans.index'), request()->routeIs('asset-scans.*'), 'Scans', 'bi-qr-code-scan');
        }
        if ($navAll || $navAssetAdmin) {
            $g['Operations'][] = $navItem(route('issuances.index'), request()->routeIs('issuances.*'), 'Issuance & Returns', 'bi-arrow-repeat');
            $g['Operations'][] = $navItem(route('forecast.index'), request()->routeIs('forecast.*'), 'Forecast', 'bi-graph-up-arrow');
            $g['Operations'][] = $navItem(route('suppliers.index'), request()->routeIs('suppliers.*'), 'Suppliers', 'bi-truck');
            $g['Insight'][] = $navItem(route('reports.index'), request()->routeIs('reports.*'), 'Reports', 'bi-graph-up');
            $g['Administration'][] = $navItem(route('access-vouchers.index'), request()->routeIs('access-vouchers.*'), 'Access Vouchers', 'bi-ticket-perforated');
            /* Printable registration QR for the office door (panel revision). */
            $g['Administration'][] = $navItem(route('registration-qr'), request()->routeIs('registration-qr'), 'Registration QR', 'bi-qr-code');
            $g['Administration'][] = $navItem(route('users.index'), request()->routeIs('users.*'), 'Users', 'bi-people');
        }
        if ($navAll) {
            $g['Administration'][] = $navItem(route('reference-data.index'), request()->routeIs('reference-data.*'), 'Reference Data', 'bi-sliders');
        }
        foreach (['Overview', 'Inventory', 'Requests', 'Operations', 'Insight', 'Administration'] as $groupLabel) {
            if (!empty($g[$groupLabel])) {
                $navGroups[] = ['label' => $groupLabel, 'items' => $g[$groupLabel]];
            }
        }
    }

    $navCode = 0;
    foreach ($navGroups as $gi => $group) {
        foreach ($group['items'] as $ii => $item) {
            $navGroups[$gi]['items'][$ii]['code'] = ++$navCode;
        }
    }

    $sideLabel = $navFmoSide ? 'Facilities Office' : 'Asset Management';
    $sideShort = $navFmoSide ? 'Facilities workspace' : 'Assets workspace';

    /* Eyebrow above the page title: workspace + the nav group of the
       current page (falls back to a sensible group for pages that are not
       in the rail, such as QR labels or notifications). */
    $currentGroup = null;
    foreach ($navGroups as $gi => $group) {
        $navGroups[$gi]['active'] = false;
        foreach ($group['items'] as $item) {
            if ($item['active']) {
                $navGroups[$gi]['active'] = true;
                $currentGroup = $currentGroup ?: $group['label'];
            }
        }
    }
    if (!$currentGroup) {
        $currentGroup = match (true) {
            request()->routeIs('notifications.*') => 'Notifications',
            request()->routeIs('qr.*', 'items.*') => 'Inventory',
            request()->routeIs('facilities.*', 'activity-proposals.*', 'requisitions.*') => 'Requests',
            request()->routeIs('allocations.*', 'departments.*', 'users.*') => 'Administration',
            default => 'Workspace',
        };
    }
    $pageHeadTitle = $pageHeading ?? $title ?? 'Dashboard';

    $__name = $navUser->name ?? 'Admin';
    $__initials = collect(explode(' ', trim($__name)))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $__role = $navUser->role ?? 'admin';
    $__roleLabel = $__role === 'fmo' ? 'FMO Staff' : ($__role === 'fmo_super_admin' ? 'FMO Super Admin' : ucwords(str_replace('_', ' ', $__role)));
    $__unread = $navUser->unreadNotifications->count();
@endphp
<a class="skip-link" href="#mainContent">Skip to content</a>
<header class="masthead">
    <div class="masthead-inner">
        <a class="mast-brand" href="{{ $navFmoSide ? route('fmo.dashboard') : route('dashboard') }}">
            <span class="mast-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></span>
            <span class="mast-words"><span class="mast-name">NU Clark</span><span class="mast-side">{{ $sideLabel }}</span></span>
        </a>
        <div class="mast-right">
            <a href="{{ route('notifications.index') }}" class="mast-notif">
                <span class="mast-notif-label">Notifications</span>
                @if($__unread)<span class="mast-notif-count">{{ $__unread }}</span>@endif
            </a>
            <div class="mast-user">
                <span class="mast-avatar">{{ strtoupper($__initials) ?: 'A' }}</span>
                <span class="mast-user-meta"><span class="mast-user-name">{{ $__name }}</span><span class="mast-user-role">{{ $__roleLabel }}</span></span>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mast-logout-form">@csrf<button class="mast-logout" title="Log out"><span>Log out</span></button></form>
            <button class="mast-burger" id="navToggle" type="button" aria-expanded="false" aria-controls="primaryNav" aria-label="Open menu"><span></span><span></span><span></span></button>
        </div>
    </div>
</header>

<nav class="navbarx" id="primaryNav" aria-label="Primary">
    <div class="navbarx-inner">
        @foreach($navGroups as $gi => $group)
            <div class="navgroup {{ $group['active'] ? 'is-current' : '' }}">
                <button class="navgroup-btn" type="button" data-navgroup="{{ $gi }}" aria-expanded="false" aria-controls="navpanel-{{ $gi }}">
                    <span>{{ $group['label'] }}</span>
                    <span class="navgroup-caret" aria-hidden="true"></span>
                </button>
                <div class="navpanel" id="navpanel-{{ $gi }}" hidden>
                    <div class="navpanel-label">{{ $group['label'] }}</div>
                    @foreach($group['items'] as $item)
                        <a class="navpanel-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['href'] }}" @if($item['active']) aria-current="page" @endif>
                            <span class="navpanel-code">{{ str_pad((string) $item['code'], 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="navpanel-name">{{ $item['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
        <span class="navbarx-spacer"></span>
        <span class="navbarx-context">{{ $currentGroup }}</span>
    </div>
</nav>

<main class="main" id="mainContent">
    <section class="page-head">
        <div class="page-head-copy">
            <div class="page-head-eyebrow">{{ $sideLabel }} &middot; {{ $currentGroup }}</div>
            <h1 class="page-head-title">{{ $pageHeadTitle }}</h1>
            @isset($subtitle)
            <p class="page-head-sub">{{ $subtitle }}</p>
            @endisset
        </div>
        @hasSection('page-actions')
        <div class="page-head-actions">@yield('page-actions')</div>
        @endif
    </section>

    @hasSection('poster')
        @yield('poster')
    @endif

    <div class="content">
        @yield('content')
    </div>
</main>
<script>
(function () {
    /* Top navigation: group dropdowns on desktop, one stacked panel on mobile. */
    var body = document.body;
    var nav = document.getElementById('primaryNav');
    var burger = document.getElementById('navToggle');
    var groups = Array.prototype.slice.call(document.querySelectorAll('.navgroup'));

    function closeGroups(except) {
        groups.forEach(function (g) {
            if (g === except) return;
            g.classList.remove('open');
            var b = g.querySelector('.navgroup-btn');
            var p = g.querySelector('.navpanel');
            if (b) b.setAttribute('aria-expanded', 'false');
            if (p) p.hidden = true;
        });
    }

    groups.forEach(function (g) {
        var btn = g.querySelector('.navgroup-btn');
        var panel = g.querySelector('.navpanel');
        if (!btn || !panel) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = g.classList.contains('open');
            closeGroups(g);
            g.classList.toggle('open', !open);
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.hidden = open;
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.navgroup')) closeGroups(null);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeGroups(null);
            setMenu(false);
        }
    });

    function setMenu(open) {
        body.classList.toggle('nav-open', open);
        if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (!open) closeGroups(null);
    }
    if (burger) burger.addEventListener('click', function () { setMenu(!body.classList.contains('nav-open')); });
    if (nav) nav.querySelectorAll('.navpanel-link').forEach(function (a) {
        a.addEventListener('click', function () { setMenu(false); });
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 991 && body.classList.contains('nav-open')) setMenu(false);
    });

    /* Tables stay readable on small screens without changing their markup. */
    document.querySelectorAll('.data-table, .content table.table').forEach(function (table) {
        if (table.closest('.table-responsive, .mobile-table-scroll')) return;
        var wrap = document.createElement('div');
        wrap.className = 'table-responsive';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
@include('auth.partials.password-toggle')
@include('partials.alerts')
</body>
</html>
