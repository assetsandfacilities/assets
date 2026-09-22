{{--
    Password UX helper for the auth screens.

    Include this once, just before </body>, on any page that has a password
    field. It does two things automatically, with no markup changes needed:

      1. Adds a show/hide (eye) button inside every <input type="password">.
      2. For any password input marked with data-pw-rules, renders a live
         checklist of the account password policy (8 characters, 1 number,
         1 symbol) that ticks off as the user types.

    Everything is scoped to .pw- prefixed classes so it cannot collide with
    Bootstrap or the existing auth page styles.
--}}
<style>
    .pw-wrap{position:relative;display:block}
    .pw-wrap > input{padding-right:48px !important}
    .pw-toggle{position:absolute;top:0;right:0;height:100%;width:46px;display:flex;align-items:center;justify-content:center;background:none;border:0;border-left:1px solid rgba(32,30,29,.18);padding:0;cursor:pointer;color:#605d5d;transition:color .12s ease,background .12s ease}
    .pw-toggle:hover{color:#201e1d;background:rgba(32,30,29,.06)}
    .pw-toggle:focus-visible{outline:2px solid #ec3013;outline-offset:-2px;border-radius:0}
    .pw-toggle svg{width:18px;height:18px;display:block;pointer-events:none}
    .pw-rules{margin-top:8px;display:flex;flex-wrap:wrap;gap:6px 16px}
    .pw-rule{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#7d7979;transition:color .12s ease}
    .pw-rule .pw-tick{width:14px;height:14px;border-radius:0;border:1.5px solid #9b9797;display:inline-flex;align-items:center;justify-content:center;font-size:9px;line-height:1;color:transparent;transition:all .12s ease}
    .pw-rule.ok{color:#201e1d}
    .pw-rule.ok .pw-tick{background:#201e1d;border-color:#201e1d;color:#f3f2f2}
</style>

<script>
(function () {
    var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    function addToggle(input) {
        if (input.dataset.pwToggled) { return; }
        input.dataset.pwToggled = '1';

        var wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.innerHTML = EYE;
        btn.setAttribute('aria-label', 'Show password');
        btn.title = 'Show password';

        btn.addEventListener('click', function () {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? EYE : EYE_OFF;
            var label = showing ? 'Show password' : 'Hide password';
            btn.setAttribute('aria-label', label);
            btn.title = label;
            input.focus();
        });

        wrap.appendChild(btn);
    }

    // Live checklist mirroring the server-side rule in AuthController::passwordRules().
    function addRules(input) {
        var box = document.createElement('div');
        box.className = 'pw-rules';
        var checks = [
            { label: 'At least 8 characters', test: function (v) { return v.length >= 8; } },
            { label: 'At least 1 number', test: function (v) { return /[0-9]/.test(v); } },
            { label: 'At least 1 symbol', test: function (v) { return /[^A-Za-z0-9]/.test(v); } }
        ];

        var nodes = checks.map(function (rule) {
            var el = document.createElement('span');
            el.className = 'pw-rule';
            el.innerHTML = '<span class="pw-tick">&#10003;</span>' + rule.label;
            box.appendChild(el);
            return { el: el, test: rule.test };
        });

        // Sits after the wrapper the toggle created, so it never lands inside it.
        (input.closest('.pw-wrap') || input).insertAdjacentElement('afterend', box);

        function sync() {
            var value = input.value;
            nodes.forEach(function (n) { n.el.classList.toggle('ok', n.test(value)); });
        }
        input.addEventListener('input', sync);
        sync();
    }

    function init() {
        document.querySelectorAll('input[type="password"]').forEach(addToggle);
        document.querySelectorAll('input[data-pw-rules]').forEach(addRules);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
