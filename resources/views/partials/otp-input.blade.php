{{--
    Six separate digit boxes for a verification code.

    The real form field is the hidden input named "code", so the controller
    keeps receiving exactly what it received before (a 6-digit string) and its
    validation rule is unchanged. Paste is blocked on the boxes, focus advances
    on entry and steps back on backspace.

    Params: $otpId (unique per form on the page).
--}}
@php
    $otpId = $otpId ?? 'otp-'.uniqid();
@endphp
<div class="otp-boxes" id="{{ $otpId }}" data-otp-group>
    @for($i = 0; $i < 6; $i++)
        <input type="text"
               class="otp-box"
               inputmode="numeric"
               pattern="[0-9]*"
               maxlength="1"
               autocomplete="off"
               aria-label="Digit {{ $i + 1 }} of 6"
               @if($i === 0) data-otp-first @endif
               data-otp-digit>
    @endfor
    <input type="hidden" name="code" data-otp-value>
</div>
@once
<script>
(function () {
    document.querySelectorAll('[data-otp-group]').forEach(function (group) {
        var boxes = Array.prototype.slice.call(group.querySelectorAll('[data-otp-digit]'));
        var hidden = group.querySelector('[data-otp-value]');

        function sync() {
            hidden.value = boxes.map(function (b) { return b.value.replace(/\D/g, ''); }).join('');
        }

        boxes.forEach(function (box, index) {
            box.addEventListener('input', function () {
                box.value = box.value.replace(/\D/g, '').slice(0, 1);
                sync();
                if (box.value && index < boxes.length - 1) boxes[index + 1].focus();
            });
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && index > 0) {
                    e.preventDefault();
                    boxes[index - 1].focus();
                    boxes[index - 1].value = '';
                    sync();
                } else if (e.key === 'ArrowLeft' && index > 0) {
                    boxes[index - 1].focus();
                } else if (e.key === 'ArrowRight' && index < boxes.length - 1) {
                    boxes[index + 1].focus();
                }
            });
            /* The code must be keyed in digit by digit — pasting is refused. */
            box.addEventListener('paste', function (e) { e.preventDefault(); });
            box.addEventListener('drop', function (e) { e.preventDefault(); });
        });

        var form = group.closest('form');
        if (form) form.addEventListener('submit', sync);
    });

    /* Resend button stays disabled, with a countdown, until the cooldown ends. */
    document.querySelectorAll('[data-otp-cooldown]').forEach(function (btn) {
        var left = parseInt(btn.getAttribute('data-otp-cooldown'), 10) || 0;
        if (left <= 0) return;
        var label = btn.querySelector('[data-otp-cooldown-label]') || btn;
        var original = label.textContent;
        btn.disabled = true;
        var tick = setInterval(function () {
            left -= 1;
            if (left <= 0) {
                clearInterval(tick);
                btn.disabled = false;
                label.textContent = original;
            } else {
                label.textContent = original + ' (' + left + 's)';
            }
        }, 1000);
        label.textContent = original + ' (' + left + 's)';
    });
})();
</script>
@endonce
