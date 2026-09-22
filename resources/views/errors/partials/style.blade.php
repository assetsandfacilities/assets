<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after{box-sizing:border-box}
    html,body{margin:0}
    body{min-height:100vh;min-height:100dvh;display:grid;grid-template-rows:auto 1fr auto;background:#f3f2f2;color:#201e1d;font-family:"Archivo",system-ui,-apple-system,"Segoe UI",Arial,sans-serif;-webkit-font-smoothing:antialiased}
    .err-bar{display:flex;align-items:center;gap:14px;padding:18px clamp(16px,5vw,44px);background:#1a1817;color:#f8f4f4}
    .err-mark{width:44px;height:44px;display:grid;place-items:center;background:transparent}
    .err-mark img{width:100%;height:100%;object-fit:contain;display:block}
    .err-brand{font-weight:800;font-size:17px;letter-spacing:-.01em}
    .err-brand span{display:block;margin-top:2px;font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#9b9797}
    .err-main{padding:clamp(40px,8vw,96px) clamp(16px,5vw,44px);max-width:880px}
    .err-code{font-weight:800;font-size:clamp(88px,16vw,180px);line-height:.85;letter-spacing:-.06em;color:#ec3013}
    .err-eyebrow{margin:28px 0 14px;font-size:10.5px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#ae1800}
    h1{margin:0;padding-top:22px;border-top:2px solid rgba(32,30,29,.4);font-weight:800;font-size:clamp(30px,4.4vw,52px);line-height:1.04;letter-spacing:-.035em}
    p{margin:16px 0 0;max-width:60ch;font-size:17px;line-height:1.55;color:#444141}
    p.reason{margin:22px 0 0;padding:14px 16px;border-left:4px solid #ec3013;background:#fff2ef;color:#7c1405;font-size:14px;word-break:break-word}
    p.reason.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px}
    .err-note{margin-top:10px;font-size:13px;color:#605d5d}
    .err-note code{background:#eae9e9;padding:1px 5px}
    .btn-back{display:inline-flex;align-items:center;gap:8px;margin-top:32px;padding:14px 22px;background:#ec3013;border:1px solid #ec3013;color:#f8f4f4;text-decoration:none;text-align:left;font-weight:800;font-size:12.5px;letter-spacing:.12em;text-transform:uppercase}
    .btn-back:hover{background:#dd2b0f;border-color:#dd2b0f}
    .btn-back:focus-visible{outline:2px solid #ec3013;outline-offset:2px}
    .err-foot{padding:18px clamp(16px,5vw,44px);border-top:1px solid #d7d3d3;font-size:12px;color:#7d7979}
</style>
