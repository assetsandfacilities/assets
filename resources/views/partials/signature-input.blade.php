@php
    $sid = $signatureId ?? ('sig-' . uniqid());
    $isRequired = $signatureRequired ?? false;
    $existing = $existingSignature ?? null;
@endphp
<div class="signature-input" id="{{ $sid }}-wrap">
    @if($existing)
        <div class="mb-2">
            <div class="tiny-2 mb-1">Current e-signature</div>
            <div class="signature-preview-box"><img src="{{ $existing }}" alt="Current e-signature"></div>
        </div>
    @endif
    <div class="row g-2">
        <div class="col-md-5">
            <label class="form-label">Upload signature image{{ $isRequired ? ' *' : '' }}</label>
            <input type="file" name="signature_file" class="form-control" accept="image/png,image/jpeg,image/webp" data-signature-file>
            <div class="field-hint mt-1">PNG/JPG/WEBP, up to 2 MB. Transparent PNG works best.</div>
        </div>
        <div class="col-md-7">
            <label class="form-label">Or draw signature{{ $isRequired ? ' *' : '' }}</label>
            <div class="signature-pad-shell">
                <canvas id="{{ $sid }}-canvas" class="signature-canvas" width="520" height="150"></canvas>
                <button type="button" class="btn-soft small-btn mt-2" id="{{ $sid }}-clear"><i class="bi bi-eraser"></i> Clear drawing</button>
            </div>
            <input type="hidden" name="signature_drawn" id="{{ $sid }}-drawn">
        </div>
    </div>
    <div class="field-hint mt-2">Use either upload or drawing. Once an approver account is created, only the proper Super Admin can replace this e-signature.</div>
</div>
<style>
.signature-preview-box{height:82px;max-width:360px;border:1px solid rgba(32,30,29,.4);border-radius:0;background:#fff;display:flex;align-items:center;justify-content:flex-start;padding:6px 10px}
.signature-preview-box img{max-width:100%;max-height:68px;object-fit:contain}
.signature-pad-shell{border:1px solid rgba(32,30,29,.4);border-radius:0;background:#fff;padding:8px}
.signature-canvas{display:block;width:100%;height:120px;touch-action:none;cursor:crosshair;border-bottom:1px dashed #9b9797;background:#fff}
</style>
<script>
(function(){
    const canvas = document.getElementById(@json($sid . '-canvas'));
    const hidden = document.getElementById(@json($sid . '-drawn'));
    const clearBtn = document.getElementById(@json($sid . '-clear'));
    if (!canvas || !hidden) return;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasInk = false;
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111827';
    function point(e){
        const r = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return {x:(t.clientX-r.left)*(canvas.width/r.width), y:(t.clientY-r.top)*(canvas.height/r.height)};
    }
    function start(e){ drawing=true; const p=point(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault(); }
    function move(e){ if(!drawing)return; const p=point(e); ctx.lineTo(p.x,p.y); ctx.stroke(); hasInk=true; hidden.value=canvas.toDataURL('image/png'); e.preventDefault(); }
    function end(){ drawing=false; if(hasInk) hidden.value=canvas.toDataURL('image/png'); }
    canvas.addEventListener('mousedown',start); canvas.addEventListener('mousemove',move); window.addEventListener('mouseup',end);
    canvas.addEventListener('touchstart',start,{passive:false}); canvas.addEventListener('touchmove',move,{passive:false}); canvas.addEventListener('touchend',end);
    clearBtn?.addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);hidden.value='';hasInk=false;});
})();
</script>
