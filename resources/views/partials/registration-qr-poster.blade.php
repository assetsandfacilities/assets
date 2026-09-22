{{--
    Printable registration QR poster, shared by the Facilities Management Office
    and Asset Management.

    Variables:
      $officeName      full office name shown under the university line
      $officeShort     short name used in the footer credit
      $lede            one line describing what the account is for
      $voucherNote     optional extra step (Asset Management voucher), or null
      $registrationUrl plain-text fallback address
      $qrImageUrl      the generated QR image

    The poster is centred on the sheet when printed. The screen preview is the
    same block, so what the administrator sees is what comes out of the printer.
--}}
<div class="qr-poster-wrap">
    <div class="note-callout no-print">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>How this works.</strong>
            <span>The QR opens the ordinary registration page, so every check still applies: the institutional email, the one-time code{{ $voucherNote ? ', and the voucher' : '' }}. Printing this poster does not create accounts by itself.</span>
        </div>
    </div>

    <article class="qr-poster" id="registrationPoster">
        <header class="qr-poster-head">
            <div class="qr-poster-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
            <div class="qr-poster-headwords">
                <div class="qr-poster-org">National University &mdash; Clark</div>
                <div class="qr-poster-office">{{ $officeName }}</div>
            </div>
        </header>

        <h2 class="qr-poster-title">Create your account</h2>
        <p class="qr-poster-lede">{{ $lede }}</p>

        <div class="qr-poster-code">
            <img src="{{ $qrImageUrl }}" alt="QR code linking to the account registration page">
        </div>

        <ol class="qr-poster-steps">
            <li>Open your phone camera and point it at the code.</li>
            <li>Tap the link that appears on screen.</li>
            <li>Register using your NU institutional email, then key in the 6-digit code sent to your inbox.</li>
            @if($voucherNote)
                <li>{{ $voucherNote }}</li>
            @endif
        </ol>

        <div class="qr-poster-url">
            <span>Or type this address in your browser</span>
            <strong>{{ $registrationUrl }}</strong>
        </div>

        <footer class="qr-poster-foot">Posted by the {{ $officeShort }} &middot; NU Clark</footer>
    </article>
</div>

@push('styles')
<style>
    .qr-poster-wrap{max-width:820px;margin:0 auto}
    .qr-poster{
        margin:0 auto;padding:44px 44px 34px;background:var(--color-bg);
        border:2px solid var(--color-text);text-align:center;
    }
    .qr-poster-head{
        display:flex;align-items:center;justify-content:center;gap:16px;
        padding-bottom:22px;border-bottom:2px solid var(--color-text);
    }
    .qr-poster-mark{width:60px;height:60px;flex:0 0 60px;display:grid;place-items:center;background:transparent}
    .qr-poster-mark img{width:100%;height:100%;object-fit:contain;display:block}
    .qr-poster-headwords{text-align:left}
    .qr-poster-org{font-family:var(--font-heading);font-weight:800;font-size:22px;letter-spacing:-.02em}
    .qr-poster-office{margin-top:3px;font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--color-neutral-700)}
    .qr-poster-title{margin:30px 0 0;font-size:clamp(34px,5vw,52px);line-height:1;letter-spacing:-.035em}
    .qr-poster-lede{margin:14px auto 0;max-width:46ch;font-size:17px;color:var(--color-neutral-800)}
    .qr-poster-code{
        margin:32px auto;padding:22px;border:1px solid var(--color-neutral-400);
        background:#fff;width:fit-content;
    }
    .qr-poster-code img{display:block;width:300px;height:300px;max-width:100%}
    /* The list stays left-aligned inside a centred block: centred list items
       are hard to read, but the block itself should sit in the middle. */
    .qr-poster-steps{
        display:inline-block;margin:0;padding-left:22px;max-width:52ch;
        text-align:left;font-size:16px;line-height:1.7;color:var(--color-text);
    }
    .qr-poster-url{
        margin:28px auto 0;max-width:52ch;padding:16px 18px;text-align:left;
        border-left:4px solid var(--color-accent);background:var(--color-surface);
    }
    .qr-poster-url span{display:block;font-size:10.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--color-neutral-700)}
    .qr-poster-url strong{display:block;margin-top:5px;font-size:17px;word-break:break-all}
    .qr-poster-foot{
        margin-top:28px;padding-top:16px;border-top:1px solid var(--color-neutral-300);
        font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--color-neutral-700);
    }

    /* ------------------------------------------------------------------
       Print. The screen layout is a centred column inside the app shell;
       on paper the shell is gone, so the poster has to re-centre itself
       against the sheet instead of inheriting the app's left edge. Colours
       are forced on so the accent bar and rules survive the print dialog's
       "background graphics" default.
       ------------------------------------------------------------------ */
    @page{margin:12mm}
    @media print{
        .no-print{display:none!important}
        html,body{width:100%;margin:0;padding:0;background:#fff}
        body *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .main,.content,.qr-poster-wrap{
            width:100%!important;max-width:100%!important;
            margin:0 auto!important;padding:0!important;float:none!important;
        }
        .qr-poster{
            width:100%;max-width:170mm;margin:0 auto!important;padding:26px 24px;
            border-width:2px;break-inside:avoid;
        }
        .qr-poster-title{font-size:40px}
        .qr-poster-code img{width:270px;height:270px}
        .qr-poster-foot{margin-top:20px}
    }
</style>
@endpush
