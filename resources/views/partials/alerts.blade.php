{{--
    System feedback: toasts, dialogs and confirmations.

    Replaces three things that used to look unfinished:
      1. the flat green "…updated successfully." bar,
      2. the bulleted red validation list,
      3. the browser's native confirm() box on every delete.

    Design choice worth knowing: success messages appear as a TOAST that slides
    in and dismisses itself, while errors open a MODAL DIALOG that must be
    acknowledged. A save confirmation shouldn't interrupt the user's next click,
    but a failure should never be missed. To make successes modal too, change
    NUAlert.flash's `toast` call to `dialog`.
--}}

<style>
    /* ---------- Toast ---------- */
    .nu-toasts{position:fixed;top:18px;right:18px;z-index:2000;display:flex;flex-direction:column;gap:10px;max-width:min(400px,calc(100vw - 36px))}
    .nu-toast{position:relative;overflow:hidden;display:flex;gap:14px;align-items:flex-start;padding:16px 16px 18px;border-radius:0;background:#f3f2f2;border:1px solid rgba(32,30,29,.4);border-left:4px solid #201e1d;box-shadow:0 12px 32px rgba(45,43,43,.22);font-family:"Archivo",system-ui,"Segoe UI",Arial,sans-serif;transform:translateX(120%);opacity:0;transition:transform .3s cubic-bezier(.22,1,.36,1),opacity .22s ease}
    .nu-toast.in{transform:translateX(0);opacity:1}
    .nu-toast.out{transform:translateX(120%);opacity:0}
    .nu-toast.error{border-left-color:#ec3013}
    .nu-toast.info{border-left-color:#7d7979}
    .nu-toast-icon{width:30px;height:30px;flex:0 0 30px;border-radius:0;display:flex;align-items:center;justify-content:center;font-size:15px;color:#f3f2f2;background:#201e1d}
    .nu-toast.success .nu-toast-icon{background:#201e1d}
    .nu-toast.error .nu-toast-icon{background:#ec3013}
    .nu-toast.info .nu-toast-icon{background:#605d5d}
    .nu-toast-body{flex:1;min-width:0}
    .nu-toast-title{margin-bottom:2px;font-size:10.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#201e1d}
    .nu-toast.error .nu-toast-title{color:#ae1800}
    .nu-toast-msg{font-size:14px;line-height:1.5;color:#2d2b2b;word-wrap:break-word}
    .nu-toast-close{background:none;border:0;color:#7d7979;font-size:20px;line-height:1;cursor:pointer;padding:0 2px}
    .nu-toast-close:hover{color:#201e1d}
    .nu-toast-close:focus-visible{outline:2px solid #ec3013;outline-offset:2px}
    .nu-toast-bar{position:absolute;left:0;bottom:0;height:3px;border-radius:0;background:#201e1d;animation:nuToastBar linear forwards}
    .nu-toast.error .nu-toast-bar{background:#ec3013}
    .nu-toast.info .nu-toast-bar{background:#7d7979}
    @keyframes nuToastBar{from{width:100%}to{width:0}}

    /* ---------- Dialog ---------- */
    .nu-backdrop{position:fixed;inset:0;background:rgba(45,43,43,.5);z-index:2100;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity .18s ease}
    .nu-backdrop.in{opacity:1}
    .nu-dialog{width:100%;max-width:460px;background:#eae9e9;border-radius:0;box-shadow:0 12px 32px rgba(45,43,43,.22);overflow:hidden;font-family:"Archivo",system-ui,"Segoe UI",Arial,sans-serif;border-top:4px solid #201e1d;transform:translateY(10px);transition:transform .22s cubic-bezier(.22,1,.36,1)}
    .nu-backdrop.in .nu-dialog{transform:translateY(0)}
    .nu-dialog.error,.nu-dialog.warn{border-top-color:#ec3013}
    .nu-dialog-head{display:flex;align-items:center;gap:14px;padding:22px 24px 16px;text-align:left;border-bottom:2px solid rgba(32,30,29,.4)}
    .nu-dialog-icon{width:40px;height:40px;flex:0 0 40px;border-radius:0;margin:0;display:flex;align-items:center;justify-content:center;font-size:19px;color:#f3f2f2;background:#201e1d}
    .nu-dialog.success .nu-dialog-icon{background:#201e1d}
    .nu-dialog.error .nu-dialog-icon{background:#ec3013}
    .nu-dialog.warn .nu-dialog-icon{background:#f3f2f2;color:#ae1800;border:2px solid #ec3013}
    .nu-dialog.info .nu-dialog-icon{background:#605d5d}
    .nu-dialog-title{margin:0;font-size:20px;font-weight:800;letter-spacing:-.015em;line-height:1.2;color:#201e1d}
    .nu-dialog-body{padding:18px 24px 4px;text-align:left;font-size:15px;color:#2d2b2b;line-height:1.55}
    .nu-dialog-list{text-align:left;margin:14px 0 0;padding:12px 14px;background:#fff2ef;border:0;border-left:4px solid #ec3013;border-radius:0;list-style:none}
    .nu-dialog-list li{font-size:14px;color:#7c1405;padding:3px 0 3px 16px;position:relative;line-height:1.5}
    .nu-dialog-list li::before{content:"";position:absolute;left:0;top:11px;width:6px;height:6px;background:#ec3013}
    .nu-dialog-foot{display:flex;justify-content:flex-end;gap:10px;padding:20px 24px 24px}
    .nu-btn{flex:0 0 auto;min-height:44px;padding:12px 18px;border-radius:0;border:1px solid transparent;font-family:inherit;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;text-align:left;cursor:pointer;transition:background .12s ease,border-color .12s ease}
    .nu-btn:focus-visible{outline:2px solid #ec3013;outline-offset:2px}
    .nu-btn-primary{background:#ec3013;border-color:#ec3013;color:#f8f4f4}
    .nu-btn-primary:hover{background:#dd2b0f;border-color:#dd2b0f}
    .nu-btn-danger{background:#ec3013;border-color:#ec3013;color:#f8f4f4}
    .nu-btn-danger:hover{background:#ae1800;border-color:#ae1800}
    .nu-btn-ghost{background:transparent;border-color:rgba(32,30,29,.4);color:#201e1d}
    .nu-btn-ghost:hover{background:rgba(32,30,29,.07)}
    @media (max-width:520px){.nu-toasts{left:12px;right:12px;max-width:none}.nu-toast{transform:translateY(-120%)}.nu-toast.in{transform:translateY(0)}.nu-toast.out{transform:translateY(-120%)}.nu-dialog-foot{flex-direction:column-reverse}.nu-btn{width:100%}}
    @media (prefers-reduced-motion:reduce){.nu-toast,.nu-dialog,.nu-backdrop{transition:none}.nu-toast-bar{animation:none}}
</style>

<div class="nu-toasts" id="nuToasts" aria-live="polite" aria-atomic="true"></div>

<script>
window.NUAlert = (function () {
    var ICONS = {
        success: '<i class="bi bi-check-lg"></i>',
        error: '<i class="bi bi-exclamation-triangle"></i>',
        warn: '<i class="bi bi-exclamation-triangle"></i>',
        info: '<i class="bi bi-info-lg"></i>'
    };

    function toast(opts) {
        var host = document.getElementById('nuToasts');
        if (!host) { return; }
        var kind = opts.kind || 'success';
        var duration = opts.duration || 4500;

        var el = document.createElement('div');
        el.className = 'nu-toast ' + kind;
        el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        el.innerHTML =
            '<div class="nu-toast-icon">' + (ICONS[kind] || ICONS.info) + '</div>' +
            '<div class="nu-toast-body">' +
                '<div class="nu-toast-title"></div>' +
                '<div class="nu-toast-msg"></div>' +
            '</div>' +
            '<button type="button" class="nu-toast-close" aria-label="Dismiss">&times;</button>' +
            '<div class="nu-toast-bar" style="animation-duration:' + duration + 'ms"></div>';

        el.querySelector('.nu-toast-title').textContent = opts.title || (kind === 'error' ? 'Something went wrong' : 'Done');
        el.querySelector('.nu-toast-msg').textContent = opts.message || '';
        host.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('in'); });

        var timer = setTimeout(close, duration);
        function close() {
            clearTimeout(timer);
            el.classList.add('out');
            setTimeout(function () { el.remove(); }, 340);
        }
        el.querySelector('.nu-toast-close').addEventListener('click', close);
        return close;
    }

    function dialog(opts) {
        return new Promise(function (resolve) {
            var kind = opts.kind || 'info';
            var backdrop = document.createElement('div');
            backdrop.className = 'nu-backdrop';

            var listHtml = '';
            if (opts.items && opts.items.length) {
                listHtml = '<ul class="nu-dialog-list">' + opts.items.map(function (i) {
                    var li = document.createElement('li');
                    li.textContent = i;
                    return li.outerHTML;
                }).join('') + '</ul>';
            }

            backdrop.innerHTML =
                '<div class="nu-dialog ' + kind + '" role="dialog" aria-modal="true">' +
                    '<div class="nu-dialog-head">' +
                        '<div class="nu-dialog-icon">' + (ICONS[kind] || ICONS.info) + '</div>' +
                        '<div class="nu-dialog-title"></div>' +
                    '</div>' +
                    '<div class="nu-dialog-body"><span class="nu-dialog-text"></span>' + listHtml + '</div>' +
                    '<div class="nu-dialog-foot"></div>' +
                '</div>';

            backdrop.querySelector('.nu-dialog-title').textContent = opts.title || 'Notice';
            backdrop.querySelector('.nu-dialog-text').textContent = opts.message || '';

            var foot = backdrop.querySelector('.nu-dialog-foot');
            function done(value) {
                backdrop.classList.remove('in');
                setTimeout(function () { backdrop.remove(); }, 220);
                document.removeEventListener('keydown', onKey);
                resolve(value);
            }

            if (opts.confirm) {
                var cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'nu-btn nu-btn-ghost';
                cancel.textContent = opts.cancelText || 'Cancel';
                cancel.addEventListener('click', function () { done(false); });
                foot.appendChild(cancel);
            }

            var ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'nu-btn ' + (opts.danger ? 'nu-btn-danger' : 'nu-btn-primary');
            ok.textContent = opts.okText || (opts.confirm ? 'Confirm' : 'OK');
            ok.addEventListener('click', function () { done(true); });
            foot.appendChild(ok);

            function onKey(e) {
                if (e.key === 'Escape') { done(false); }
                if (e.key === 'Enter') { done(true); }
            }
            document.addEventListener('keydown', onKey);
            backdrop.addEventListener('click', function (e) { if (e.target === backdrop) { done(false); } });

            document.body.appendChild(backdrop);
            requestAnimationFrame(function () { backdrop.classList.add('in'); });
            setTimeout(function () { ok.focus(); }, 60);
        });
    }

    function confirmAction(opts) {
        return dialog(Object.assign({ kind: 'warn', confirm: true, danger: true }, opts));
    }

    return { toast: toast, dialog: dialog, confirm: confirmAction };
})();

/* Any element carrying data-confirm gets the styled dialog instead of the
   browser's native confirm(). Works for both forms and links. */
document.addEventListener('DOMContentLoaded', function () {
    /* Give every DELETE form a consistent confirmation dialog, even when an
       older page forgot to add data-confirm itself. */
    document.querySelectorAll('form').forEach(function (form) {
        var method = form.querySelector('input[name="_method"]');
        if (method && String(method.value).toUpperCase() === 'DELETE' && !form.dataset.confirm) {
            form.dataset.confirm = 'Delete this record permanently? This action cannot be undone.';
            form.dataset.confirmTitle = 'Delete record?';
            form.dataset.confirmOk = 'Delete permanently';
        }
    });

    function confirmItems(el) {
        if (!el.dataset.confirmItems) return [];
        try { return JSON.parse(el.dataset.confirmItems); } catch (e) { return []; }
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') { return; }
            e.preventDefault();
            NUAlert.confirm({
                title: form.dataset.confirmTitle || 'Please confirm',
                message: form.dataset.confirm,
                items: confirmItems(form),
                kind: form.dataset.confirmKind || 'warn',
                danger: form.dataset.confirmDanger !== 'false',
                okText: form.dataset.confirmOk || 'Yes, continue'
            }).then(function (ok) {
                if (ok) {
                    form.dataset.confirmed = '1';
                    form.dataset.unsavedSubmitted = '1';
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('a[data-confirm]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            NUAlert.confirm({
                title: link.dataset.confirmTitle || 'Please confirm',
                message: link.dataset.confirm,
                items: confirmItems(link),
                kind: link.dataset.confirmKind || 'warn',
                danger: link.dataset.confirmDanger !== 'false',
                okText: link.dataset.confirmOk || 'Yes, continue'
            }).then(function (ok) { if (ok) { window.location.href = link.href; } });
        });
    });


    /* Long forms: warn before discarding typed changes. Cancel buttons receive
       a styled in-app dialog; browser/tab navigation keeps the browser's native
       safety prompt because browsers intentionally do not allow custom text. */
    document.querySelectorAll('form[data-unsaved-warning]').forEach(function (form) {
        var dirty = false;
        var initial = new FormData(form);
        function markDirty() { dirty = true; }
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.addEventListener('input', markDirty);
            field.addEventListener('change', markDirty);
        });
        form.addEventListener('submit', function (e) {
            if (!e.defaultPrevented) form.dataset.unsavedSubmitted = '1';
        });
        form.querySelectorAll('a[data-unsaved-cancel]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (!dirty || form.dataset.unsavedSubmitted === '1') return;
                e.preventDefault();
                NUAlert.confirm({
                    title: 'Discard unsaved changes?',
                    message: 'You have changes on this form that have not been submitted yet.',
                    okText: 'Discard changes',
                    cancelText: 'Keep editing'
                }).then(function (ok) {
                    if (ok) {
                        form.dataset.unsavedSubmitted = '1';
                        window.location.href = link.href;
                    }
                });
            });
        });
        window.addEventListener('beforeunload', function (e) {
            if (!dirty || form.dataset.unsavedSubmitted === '1') return;
            e.preventDefault();
            e.returnValue = '';
        });
    });
});
</script>

{{-- Server-side flash messages, handed to the component above. --}}
@if(session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        NUAlert.toast({ kind: 'success', title: 'Success', message: @json(session('success')) });
    });
</script>
@endif

@if(session('info'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        NUAlert.toast({ kind: 'info', title: 'Heads up', message: @json(session('info')) });
    });
</script>
@endif

@if(session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        NUAlert.dialog({ kind: 'error', title: 'Something went wrong', message: @json(session('error')) });
    });
</script>
@endif

@if(isset($errors) && $errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var items = @json($errors->all());
        NUAlert.dialog({
            kind: 'error',
            title: items.length > 1 ? 'Please fix these ' + items.length + ' items' : 'Please check this',
            message: items.length > 1 ? 'Your changes were not saved.' : items[0],
            items: items.length > 1 ? items : [],
            okText: 'Got it'
        });
    });
</script>
@endif
