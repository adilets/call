<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Pay with Zelle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon2.png') }}" />
    <style>
        :root{ --accent:#2563eb; }
        .card{border-radius:18px;border:1px solid rgba(15,23,42,.08);background:#fff;box-shadow:0 12px 28px -18px rgba(2,6,23,.25)}
        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .6rem;border-radius:999px;font-size:.72rem;font-weight:600;background:#f1f5f9;color:#334155}
        .input{width:100%;border-radius:.8rem;border:1px solid #e2e8f0;padding:.55rem .7rem;font-size:.9rem;background:#f8fafc}
        .copy-btn{display:inline-flex;align-items:center;gap:.35rem;border:1px solid #e2e8f0;border-radius:.7rem;padding:.4rem .55rem;font-size:.8rem;color:#475569;background:#fff}
        .copy-btn:hover{background:#f8fafc}
        .section-title{font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-top:1rem}
        .section-text{font-size:.9rem;color:#475569}
        .timer-box{margin-top:1rem;border:1px solid #e2e8f0;border-radius:1rem;padding:.75rem;display:flex;gap:.75rem;align-items:center;justify-content:space-between;background:#f8fafc}
        .timer-time{font-size:1.1rem;font-weight:700}
        .status-pill{background:#e2e8f0;color:#334155;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;font-weight:600}
        .timer-extend{font-size:.75rem;color:#2563eb;text-decoration:underline}
        .btn-primary{margin-top:1rem;width:100%;border-radius:.9rem;background:#111827;color:#fff;padding:.8rem 1rem;font-weight:700}
        .btn-primary:disabled{opacity:.6;cursor:default}
        .toast{position:fixed;bottom:18px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:.5rem .8rem;border-radius:.75rem;font-size:.8rem;opacity:0;transition:opacity .18s}
        .toast.show{opacity:1}
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
@php
    $recipient = $details['recipient'] ?? '';
    $email = $details['email'] ?? '';
    $memo = $details['id'] ?? '';
    $qrUrl = $details['qrUrl'] ?? '';
@endphp

<div class="max-w-2xl mx-auto p-4">
    <div class="mb-2">
        <a href="{{ route('payment.page', ['token' => request()->route('token')]) }}" class="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-800">
            <span aria-hidden="true">←</span>
            Back to payment methods
        </a>
    </div>
    <h1 class="text-2xl font-semibold">Pay with Zelle®</h1>
    <p class="text-slate-500 mt-1">Scan the QR code or send a payment manually using your online banking.</p>

    <div class="card mt-4 p-4">
        <div class="flex items-center justify-between">
            <span class="font-semibold">Zelle®</span>
            <span class="badge">US bank accounts only</span>
        </div>

        <div class="section-title">Scan &amp; pay</div>
        <p class="section-text">Scan this code in your bank's app to pay <strong>{{ $recipient }}</strong> using Zelle.</p>
        <div class="mt-3 flex justify-center">
            @if($qrUrl)
                <img src="{{ $qrUrl }}" alt="Zelle QR code" class="h-[25rem] w-auto rounded-xl border border-slate-200 bg-white p-2">
            @else
                <div class="h-44 w-44 rounded-xl border border-dashed border-slate-300 flex items-center justify-center text-slate-400 text-xs">
                    QR code unavailable
                </div>
            @endif
        </div>

        <div class="section-title">Or send manually</div>
        <p class="section-text">Use these details if you prefer to type the payment instead of scanning.</p>

        <div class="mt-3 space-y-2">
            <div class="flex items-center gap-2">
                <div class="w-28 text-sm text-slate-500">Recipient</div>
                <input id="zelleRecipient" class="input" value="{{ $recipient }}" readonly>
                <button class="copy-btn" data-copy="#zelleRecipient">Copy</button>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-28 text-sm text-slate-500">Zelle email</div>
                <input id="zelleEmail" class="input" value="{{ $email }}" readonly>
                <button class="copy-btn" data-copy="#zelleEmail">Copy</button>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-28 text-sm text-slate-500">Amount</div>
                <input id="zelleAmount" class="input" value="${{ $amount }}" readonly>
                <button class="copy-btn" data-copy="#zelleAmount">Copy</button>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-28 text-sm text-slate-500">Memo</div>
                <input id="zelleMemo" class="input" value="{{ $memo }}" readonly>
                <button class="copy-btn" data-copy="#zelleMemo">Copy</button>
            </div>
        </div>

        <div class="timer-box" aria-live="polite">
            <div class="flex items-center gap-3">
                <span id="zelleDot" class="h-3 w-3 rounded-full bg-emerald-500"></span>
                <div>
                    <div class="timer-time" id="zelleTimer">15:00</div>
                    <div class="text-xs text-slate-500">Please send payment within this time.</div>
                </div>
            </div>
            <div class="text-right">
                <div class="status-pill" id="zelleStatus">Awaiting payment</div>
                <button type="button" class="timer-extend" id="zelleExtend">Extend time +10 min</button>
            </div>
        </div>

        <button class="btn-primary" id="zellePaidBtn">
            ✅ I HAVE PAID
        </button>

        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
            <div class="font-semibold mb-1">Short instructions</div>
            <ul class="list-disc ml-5 space-y-1">
                <li>Send the exact <strong>amount</strong> shown above from your own bank account.</li>
                <li>In the <strong>memo</strong> / notes field, enter only the order reference (no website or product names).</li>
                <li>After you send the payment, click <strong>“I HAVE PAID”</strong> so we can start verification.</li>
            </ul>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function toast(msg){
        if (!toastEl) return;
        toastEl.textContent = msg || '';
        toastEl.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(()=>toastEl.classList.remove('show'), 1400);
    }

    async function copyToClipboard(text){
        try {
            if (navigator && navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text || '');
                return true;
            }
        } catch (_) {}
        try {
            const ta = document.createElement('textarea');
            ta.value = String(text || '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            const ok = document.execCommand && document.execCommand('copy');
            document.body.removeChild(ta);
            return !!ok;
        } catch (_) { return false; }
    }

    document.querySelectorAll('.copy-btn').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
            const sel = btn.getAttribute('data-copy');
            const el = sel && document.querySelector(sel);
            if(!el) return;
            const text = (el.value ?? el.textContent ?? '').toString();
            const ok = await copyToClipboard(text);
            toast(ok ? 'Copied' : 'Copy failed');
        });
    });

    let zelleDeadlineMs = Date.now() + 15 * 60 * 1000;
    let zelleTimerInt = null;
    function renderZelleTimer(){
        const left = Math.max(0, zelleDeadlineMs - Date.now());
        const m = Math.floor(left / 60000);
        const s = Math.floor((left % 60000) / 1000);
        const text = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        const tEl = document.getElementById('zelleTimer');
        if (tEl) tEl.textContent = text;
        if (left <= 0) {
            clearInterval(zelleTimerInt);
            const st = document.getElementById('zelleStatus');
            if (st) st.textContent = 'Hold expired';
        }
    }
    zelleTimerInt = setInterval(renderZelleTimer, 1000);
    renderZelleTimer();

    document.getElementById('zelleExtend')?.addEventListener('click', ()=>{
        zelleDeadlineMs += 10 * 60 * 1000;
        renderZelleTimer();
        document.getElementById('zelleExtend')?.setAttribute('disabled','true');
    });

    document.getElementById('zellePaidBtn')?.addEventListener('click', ()=>{
        const st = document.getElementById('zelleStatus');
        if (st) st.textContent = 'Payment sent';
        const btn = document.getElementById('zellePaidBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'PROCESSING…'; }
    });
</script>
</body>
</html>

