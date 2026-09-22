<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <title>403 - Not Allowed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('errors.partials.style')
</head>
<body>
    <header class="err-bar">
        <div class="err-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
        <div class="err-brand">NU Clark<span>Asset Management</span></div>
    </header>
    <main class="err-main">
        <div class="err-code">403</div>
        <div class="err-eyebrow">Error 403 · Permission</div>
        <h1>Action Not Allowed</h1>
        <p>You don't have permission to do that right now.</p>
        @if($exception->getMessage())
            <p class="reason">{{ $exception->getMessage() }}</p>
        @endif
        <a class="btn-back" href="{{ url()->previous() }}">Go Back</a>
    </main>
    <footer class="err-foot">National University — Clark</footer>
</body>
</html>
