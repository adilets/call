<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Local Payment — EU / UK / AU / US / CA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon2.png') }}" />
    <style>
        :root{ --shadow-1:0 10px 24px -16px rgba(2,6,23,.24); --ring-blue:#93c5fd; }
        .card{ box-shadow:var(--shadow-1); position:relative; }
        .btn{display:inline-flex;align-items:center;gap:.45rem;border:1px solid #cbd5e1;background:#fff;color:#334155;padding:.5rem .8rem;border-radius:.8rem;font-weight:600;font-size:.9rem}
        .btn:hover{background:#f8fafc}
        .pill{border:1px solid #e2e8f0;border-radius:.8rem;padding:.25rem .55rem;font-size:.8rem;line-height:1}
        .mono{font-variant-numeric:tabular-nums;letter-spacing:.02em;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}

        .value-scroll{white-space:normal;overflow-wrap:anywhere}
        @media (min-width:640px){
            .value-scroll{white-space:nowrap;overflow-x:auto;scrollbar-width:none}
            .value-scroll::-webkit-scrollbar{display:none}
        }

        .toast{
            position:fixed;
            bottom:16px;
            left:50%;
            transform:translateX(-50%);
            background:#0b7;
            color:#fff;
            padding:.55rem .8rem;
            border-radius:.7rem;
            font-weight:600;
            box-shadow:0 10px 24px -16px rgba(2,6,23,.4);
            opacity:0;
            transition:opacity .18s;
            z-index:60
        }
        .toast.show{opacity:1}
        body[data-ready="0"] .app{opacity:0}
        body[data-ready="1"] .app{opacity:1;transition:opacity .12s}
        .focus-ring:focus{outline:none;box-shadow:0 0 0 3px var(--ring-blue)}
        .need-ref-first{border-color:#93c5fd}
        @keyframes softPulse{0%{box-shadow:0 0 0 0 rgba(59,130,246,.45)}70%{box-shadow:0 0 0 10px rgba(59,130,246,0)}100%{box-shadow:0 0 0 0 rgba(59,130,246,0)}}
        .ref-pulse{animation:softPulse 1.6s ease-in-out infinite;border-color:#93c5fd!important}

        .sticky-ref{
            position:fixed;
            left:0;
            right:0;
            bottom:0;
            z-index:40;
            max-width:900px;
            margin:0 auto 0.75rem;
            padding:.55rem .85rem;
            background:#b91c1c;
            color:#fff;
            border-radius:0.85rem;
            box-shadow:0 20px 40px -20px rgba(15,23,42,.6);

            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:.5rem;

            opacity:0;
            transform:translateY(12px);
            pointer-events:none;
            transition:opacity .22s ease, transform .22s ease;
        }
        .sticky-ref.show{
            opacity:1;
            transform:translateY(0);
            pointer-events:auto;
        }

        .step{display:flex;align-items:center;gap:.5rem}
        .step .dot{width:22px;height:22px;border-radius:9999px;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#94a3b8;background:#fff}
        .step.active .dot{border-color:#60a5fa;color:#1d4ed8}
        .step.done .dot{background:#22c55e;border-color:#22c55e;color:#fff}
        .step .label{font-size:.8rem;color:#64748b;font-weight:600}
        .step.active .label{color:#1d4ed8}
        .step.done .label{color:#16a34a}

        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .45rem;border-radius:.5rem;font-size:.75rem;font-weight:600}
        .badge.ok{background:#dcfce7;color:#166534}
        .badge.no{background:#fee2e2;color:#991b1b}
    </style>
</head>
<body class="bg-slate-50 text-slate-900" data-ready="0">

<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
    <symbol id="i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="10" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><rect x="5" y="5" width="10" height="10" rx="2" fill="none" stroke="currentColor" stroke-opacity=".55" stroke-width="1.2"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V6l7-3z" fill="none" stroke="#94a3b8" stroke-width="1.4"/><path d="M9.2 12.5l2.1 2.1 3.9-4.1" fill="none" stroke="#16a34a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v14m0 0l-4-4m4 4l4-4M5 21h14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><path d="M7 9V4h10v5M7 17h10M5 13h14a2 2 0 002-2v-1a2 2 0 00-2-2H5a2 2 0 00-2 2v1a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-bank" viewBox="0 0 24 24"><path d="M5 9l7-4 7 4M4 10h16M6 10v6m4-6v6m4-6v6m4-6v6M4 20h16" fill="none" stroke="#64748B" stroke-width="1.4" stroke-linecap="round"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="#0EA5E9" stroke-width="1.4"/><path d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18" fill="none" stroke="#0EA5E9" stroke-width="1" stroke-linecap="round"/></symbol>
    <symbol id="i-card" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2.5" fill="none" stroke="#2563EB" stroke-width="1.4"/><path d="M7 10h10M7 14h6" fill="none" stroke="#2563EB" stroke-width="1.4" stroke-linecap="round"/></symbol>
    <symbol id="i-hash" viewBox="0 0 24 24"><path d="M9 3L7 21M17 3l-2 18M4 9h16M3 15h16" fill="none" stroke="#475569" stroke-width="1.6" stroke-linecap="round"/></symbol>
    <symbol id="i-route" viewBox="0 0 24 24"><path d="M5 6h8l2 3 2-3h2M5 18h14" fill="none" stroke="#16a34a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-id" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="#7c3aed" stroke-width="1.4"/><path d="M8 10h8M8 14h5" fill="none" stroke="#7c3aed" stroke-width="1.4" stroke-linecap="round"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="#db2777" stroke-width="1.4"/><path d="M3 8l9 6 9-6" fill="none" stroke="#db2777" stroke-width="1.4" stroke-linecap="round"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#fef3c7" stroke="#f59e0b" stroke-width="1.4"/><path d="M12 7v6M12 16h.01" stroke="#b45309" stroke-width="2" stroke-linecap="round"/></symbol>
</svg>

<div class="app mx-auto max-w-3xl p-4 pb-16">
    <div id="stickyRef" class="sticky-ref text-[14px] font-semibold">
        <div class="flex items-center gap-2">
            <svg width="18" height="18" class="opacity-90"><use href="#i-alert"/></svg>
            <span>Use this reference only:</span>
            <span class="mono font-extrabold" id="stickyRefNum">{{ $referenceNumber }}</span>
            <span>— digits only</span>
        </div>
        <button id="stickyClose" class="ml-2 inline-flex h-6 w-6 items-center justify-center rounded-full border border-red-200/80 text-xs text-red-50 hover:bg-red-900/60 hover:border-red-100" aria-label="Close">
            ✕
        </button>
    </div>

    <div class="mb-3 flex items-center gap-2.5">
        <div class="h-9 w-9 rounded-full bg-white shadow ring-1 ring-slate-200 flex items-center justify-center text-lg">💳</div>
        <div>
            <h1 class="text-lg font-semibold flex items-center gap-2">Local Payment <span class="text-slate-400">(EU / UK / AU / US / CA)</span></h1>
            <p class="text-[13px] text-slate-500 leading-5">Pay easily via SEPA, ACH, FPS, Interac or local bank transfer.</p>
        </div>
    </div>

    <div id="flags" class="mb-4 grid grid-cols-3 sm:grid-cols-5 gap-2"></div>

    <div class="mb-3 flex items-center gap-4">
        <div class="step" id="step1"><div class="dot">1</div><div class="label">Copy reference</div></div>
        <div class="h-px flex-1 bg-slate-200"></div>
        <div class="step" id="step2"><div class="dot">2</div><div class="label">Send transfer</div></div>
        <div class="h-px flex-1 bg-slate-200"></div>
        <div class="step" id="step3"><div class="dot">3</div><div class="label">Confirm &amp; return</div></div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 card">
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
            <div>
                <h2 id="label" class="text-[15px] font-semibold leading-5">Europe (SEPA)</h2>
                <p id="subtitle" class="text-[12px] text-slate-500">Pay via SEPA local transfer • ETA ~0–24h</p>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="pill flex items-center gap-1 text-slate-700"><svg width="14" height="14"><use href="#i-shield"/></svg><span>Secure</span></div>
                <div id="currency" class="pill text-slate-600">Currency: EUR</div>
            </div>
        </div>

        <div class="mt-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-3 flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] uppercase tracking-wide text-emerald-700 font-semibold">Amount to pay</div>
                <div id="amountValue" class="mt-1 mono text-emerald-900 text-xl font-extrabold">EUR&nbsp;149.90</div>
            </div>
            <button id="copyAmount" class="btn border-emerald-300 text-emerald-700 shrink-0"><svg width="14" height="14"><use href="#i-copy"/></svg> Copy</button>
        </div>

        <div id="refBox" class="mt-2.5 rounded-2xl border border-amber-300 bg-amber-50 p-3">
            <div class="mb-1 text-[10px] uppercase tracking-wide text-amber-700 font-semibold flex items-center gap-1.5">
                <svg width="16" height="16"><use href="#i-alert"/></svg>
                Reference (Invoice Number) — enter this only
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div id="referenceValue" class="value-scroll mono font-extrabold text-amber-900 text-xl break-words" title="{{ $referenceNumber }}">{{ $referenceNumber }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <button id="copyRef" class="btn border-amber-300 text-amber-800 focus-ring shrink-0">
                        <svg width="14" height="14"><use href="#i-copy"/></svg> Copy
                    </button>
                </div>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <span class="badge ok">✔ DO: <span class="mono">{{ $referenceNumber }}</span></span>
                <span class="badge no">✖ DON’T: <span class="mono">INV-{{ $referenceNumber }}</span></span>
                <span class="badge no">✖ DON’T: <span class="mono">{{ $referenceNumber }} PRODUCT NAME</span></span>
            </div>
            <p class="mt-2 text-[13px] text-amber-900">
                If this number is missing or changed in your bank memo, we may not be able to detect your payment automatically.
            </p>
        </div>

        <div id="fields" class="mt-3 grid gap-2.5"></div>

        <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 text-[13px] text-rose-900 leading-5">
            <div class="mb-1 flex items-center gap-1.5 text-[12px] font-semibold uppercase tracking-wide">
                <svg width="15" height="15"><use href="#i-alert"/></svg>
                Important: how to send your transfer
            </div>
            <ul class="list-disc pl-4 space-y-1.5">
                <li>
                    Please make the payment to the bank account above.
                </li>
                <li>
                    Send the transfer only from your personal bank account (business accounts not allowed).
                </li>
                <li>
                    Use the reference <strong>exactly as shown above – digits only, no extra text</strong>.
                </li>
                <li>
                    After you send the transfer, click <strong>I HAVE SENT THE TRANSFER</strong> below to finish and go back to the website.
                </li>
            </ul>
        </div>

        <div class="mt-3.5 flex flex-wrap items-center justify-end gap-1.5">
            <button id="downloadInvoice" class="btn shrink-0"><svg width="14" height="14"><use href="#i-download"/></svg> Download .txt</button>
            <button id="printInvoice" class="btn shrink-0"><svg width="14" height="14"><use href="#i-print"/></svg> Print / Save PDF</button>
            <button id="paidBtn" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-1.5 text-[13px] font-semibold text-white shadow hover:bg-blue-700 active:scale-[.98] focus-ring shrink-0 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <svg width="14" height="14"><use href="#i-check"/></svg> I HAVE SENT THE TRANSFER
            </button>
            <p class="w-full text-right text-[11px] text-slate-500 mt-1">
                Click this after sending the transfer so we can start checking your payment.
            </p>
        </div>
    </div>
</div>

<div id="modal" class="fixed inset-0 z-40 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative z-10 w-[92%] max-w-md rounded-2xl bg-white p-4 shadow-xl border border-slate-200">
        <h3 class="text-base font-semibold mb-2">Quick confirmation</h3>
        <div class="mb-3">
            <label class="text-sm text-slate-700">Type your {{ strlen($referenceNumber) }}-digit reference to confirm</label>
            <input id="confirmRefInput" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-[14px] mono focus-ring" maxlength="12" placeholder="{{ $referenceNumber }}"/>
            <p id="confirmRefMsg" class="mt-1 text-[13px]"></p>
        </div>
        <ul class="text-sm text-slate-700 space-y-2 mb-3">
            <li>
                <label class="inline-flex items-start gap-2">
                    <input id="cb1" type="checkbox" class="mt-1">
                    <span>I used <strong>only</strong> the invoice number as memo/reference</span>
                </label>
            </li>
            <li>
                <label class="inline-flex items-start gap-2">
                    <input id="cb4" type="checkbox" class="mt-1">
                    <span>The transfer was sent <strong>from my own personal bank account (not from a company account)</strong></span>
                </label>
            </li>
            <li>
                <label class="inline-flex items-start gap-2">
                    <input id="cb2" type="checkbox" class="mt-1">
                    <span>Amount &amp; currency are correct</span>
                </label>
            </li>
            <li>
                <label class="inline-flex items-start gap-2">
                    <input id="cb3" type="checkbox" class="mt-1">
                    <span>I saved a receipt from my bank</span>
                </label>
            </li>
        </ul>
        <div class="flex justify-end gap-2">
            <button id="closeModal" class="btn">Close</button>
            <button id="submitModal" class="btn" style="border-color:#60a5fa;color:#1d4ed8">Submit</button>
        </div>
    </div>
</div>

<div id="confirmFile" class="fixed inset-0 z-40 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative z-10 w-[92%] max-w-md rounded-2xl bg-white p-4 shadow-xl border border-slate-200">
        <h3 class="text-base font-semibold mb-2">Confirm reference usage</h3>
        <p class="text-sm text-slate-700">
            Please confirm you will enter <span class="mono font-bold" id="confirmFileNum">{{ $referenceNumber }}</span> as the only payment reference (no words or symbols).
        </p>
        <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
            <input id="confirmFileCb" type="checkbox"> I confirm
        </label>
        <div class="flex justify-end gap-2 mt-3">
            <button id="cancelFile" class="btn">Cancel</button>
            <button id="okFile" class="btn" style="border-color:#60a5fa;color:#1d4ed8">Continue</button>
        </div>
    </div>
</div>

<div id="toast" class="toast" role="status" aria-live="polite">Copied ✓</div>

<script>
    const regionsRaw = @json($regions);

    const regions = Array.isArray(regionsRaw)
        ? regionsRaw
        : Object.keys(regionsRaw)
            .filter((key) => !Number.isNaN(Number(key)))
            .map((key) => regionsRaw[key]);

    const defaultReference = {{$referenceNumber}};

    const flagsRoot = document.getElementById('flags');
    const labelEl = document.getElementById('label');
    const subtitleEl = document.getElementById('subtitle');
    const currencyEl = document.getElementById('currency');
    const fieldsEl = document.getElementById('fields');
    const refBox = document.getElementById('refBox');
    const refEl = document.getElementById('referenceValue');
    const copyRefBtn = document.getElementById('copyRef');
    const amountEl = document.getElementById('amountValue');
    const copyAmountBtn = document.getElementById('copyAmount');
    const toast = document.getElementById('toast');
    const stickyRef = document.getElementById('stickyRef');
    const stickyRefNum = document.getElementById('stickyRefNum');
    const stickyClose = document.getElementById('stickyClose');

    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');

    const fileModal = document.getElementById('confirmFile');
    const confirmFileNum = document.getElementById('confirmFileNum');
    const confirmFileCb = document.getElementById('confirmFileCb');
    const cancelFile = document.getElementById('cancelFile');
    const okFile = document.getElementById('okFile');

    function makeRefNumber(){ return Math.floor(Math.random()*1e6).toString().padStart(6,'0'); }

    function showToast(msg){
        const offset = (stickyRef && stickyRef.classList.contains('show') && !stickyClosed) ? 80 : 16;
        toast.style.bottom = offset + 'px';
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(()=>toast.classList.remove('show'),5000);
    }

    function copy(text){
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => showToast('Copied ✓'));
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        const selection = document.getSelection();
        const selected = selection.rangeCount > 0 ? selection.getRangeAt(0) : false;
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        if (selected) {
            selection.removeAllRanges();
            selection.addRange(selected);
        }
        showToast('Copied ✓');
    }

    let refCopied = false;
    let stickyClosed = false;

    function isMono(label){
        return /(IBAN|Account|Routing|BIC|SWIFT|Sort code|BSB|Transit|Institution|Interac)/i.test(label);
    }

    function fieldRow(label, value, iconId){
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm';
        row.innerHTML = `
      <div class="min-w-0 flex w-full items-center gap-3">
        <span class="shrink-0 text-slate-500">${ iconId ? `<svg width="16" height="16"><use href="${iconId}"/></svg>` : `<svg width="16" height="16"><use href="#i-bank"/></svg>` }</span>
        <span class="shrink-0 text-[11px] uppercase tracking-wide text-slate-500">${label}</span>
        <div class="min-w-0 flex-1">
          <div class="value-scroll ${isMono(label)?'mono':''} text-slate-900 text-[14px] sm:text-[17px] md:text-[19px]" title="${value}">${value}</div>
        </div>
        <button class="btn shrink-0 ml-2 copy-btn ${refCopied ? '' : 'need-ref-first'}"
          data-copy="${value}"
          title="Copy the Reference first — you must paste the reference as the only memo.">
          <svg width="14" height="14"><use href="#i-copy"/></svg> Copy
        </button>
      </div>
    `;
        const btn = row.querySelector('.copy-btn');
        btn.addEventListener('click', ()=>{
            if(!refCopied){
                showStickyRef();
                showToast('Copy the Reference first');
                return;
            }
            copy(btn.dataset.copy);
            btn.innerHTML = `<svg width="14" height="14"><use href="#i-check"/></svg> Copied`;
            btn.classList.remove('need-ref-first');
            setTimeout(()=> btn.innerHTML = `<svg width="14" height="14"><use href="#i-copy"/></svg> Copy`, 1100);
        });
        return row;
    }

    function renderAmount(r){
        amountEl.textContent = `${r.currency}\u00A0${r.amountBase.toFixed(2)}`;
    }

    function renderRegion(r){
        labelEl.textContent = r.label;
        subtitleEl.textContent = r.subtitle;
        currencyEl.textContent = `Currency: ${r.currency}`;
        renderAmount(r);
        fieldsEl.innerHTML = '';
        r.details.forEach(d => fieldsEl.appendChild(fieldRow(d.k, d.v, d.icon)));
        document.querySelectorAll('.copy-btn').forEach(b => {
            if(!refCopied) b.classList.add('need-ref-first');
        });
    }

    regions.forEach(r=>{
        const b=document.createElement('button');
        b.dataset.key=r.key;
        b.className='flex flex-col items-center rounded-2xl border px-3 py-1.5 text-center transition border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
        b.innerHTML=`<span class="text-xl mb-0.5" aria-hidden="true">${r.flag}</span><span class="text-[11px] font-medium leading-4">${r.key}</span>`;
        b.addEventListener('click',()=> selectRegion(r.key));
        flagsRoot.appendChild(b);
    });

    function selectRegion(key){
        const r = regions.find(x=>x.key===key)||regions[0];
        [...flagsRoot.children].forEach(btn=>{
            const act = btn.dataset.key===r.key;
            btn.className = `flex flex-col items-center rounded-2xl border px-3 py-1.5 text-center transition ${
                act
                    ? 'border-blue-400 bg-blue-50 text-blue-700 shadow'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
            }`;
        });
        renderRegion(r);
    }

    let currentRef = defaultReference;
    function setRef(n6){
        currentRef = n6;
        refEl.textContent = n6;
        refEl.title = n6;
        stickyRefNum.textContent = n6;
        confirmFileNum.textContent = n6;
    }
    setRef(currentRef);

    function pulseRefBox(ms=4000){
        refBox.classList.add('ref-pulse');
        setTimeout(()=>refBox.classList.remove('ref-pulse'), ms);
    }

    function showStickyRef(){
        if(stickyClosed) return;
        stickyRef.classList.add('show');
    }

    pulseRefBox(4000);

    setTimeout(()=>{ showStickyRef(); }, 50);

    copyRefBtn.addEventListener('click', ()=>{
        copy(currentRef);
        refCopied=true;
        updateStepper();
        document.querySelectorAll('.copy-btn').forEach(b=>b.classList.remove('need-ref-first'));
        copyRefBtn.innerHTML=`<svg width="14" height="14"><use href="#i-check"/></svg> Copied`;
        showStickyRef();
        showToast(`Copied: ${currentRef}. Enter exactly these ${String(currentRef).length} digits in your bank memo — nothing else.`);
        setTimeout(()=> copyRefBtn.innerHTML=`<svg width="14" height="14"><use href="#i-copy"/></svg> Copy`,1100);
    });

    copyAmountBtn.addEventListener('click', ()=>{
        copy(amountEl.textContent.replace(/\u00A0/g,' '));
        copyAmountBtn.innerHTML=`<svg width="14" height="14"><use href="#i-check"/></svg> Copied`;
        setTimeout(()=> copyAmountBtn.innerHTML=`<svg width="14" height="14"><use href="#i-copy"/></svg> Copy`,1100);
    });

    let pendingFileAction = null;
    function openFileConfirm(action){
        pendingFileAction = action;
        confirmFileCb.checked=false;
        fileModal.classList.remove('hidden');
        fileModal.classList.add('flex');
    }
    document.getElementById('downloadInvoice').addEventListener('click', ()=> openFileConfirm('download'));
    document.getElementById('printInvoice').addEventListener('click', ()=> openFileConfirm('print'));
    cancelFile.addEventListener('click', ()=>{
        fileModal.classList.add('hidden');
        fileModal.classList.remove('flex');
    });
    okFile.addEventListener('click', ()=>{
        if(!confirmFileCb.checked){
            showToast('Please confirm first');
            return;
        }
        fileModal.classList.add('hidden');
        fileModal.classList.remove('flex');
        if(pendingFileAction==='download'){ doDownload(); }
        else if(pendingFileAction==='print'){ window.print(); }
    });

    function doDownload(){
        const r = regions.find(x => x.label === labelEl.textContent) || regions[0];
        const lines = r.details.map(d => `${d.k}: ${d.v}`);
        const ts = new Date().toISOString().replace('T',' ').slice(0,19);
        const text = [
            '=== PAYMENT INVOICE ===',
            `Issued at: ${ts}`, '',
            'Merchant:','  REXPRESS S.R.L.','',
            'Payment Method:',`  Local Bank Transfer — ${r.label}`,`  Currency: ${r.currency}`,`  Amount: ${amountEl.textContent.replace(/\u00A0/g,' ')}`,'',
            'Reference (Invoice Number):',`  ${currentRef}`,'',
            'Bank Details:',...lines.map(l=>'  '+l),'',
            'Reference rule:','  Use ONLY the invoice number as the payment reference (memo).',
            '  Any extra text may delay or prevent automatic matching.','',
            'Notes:','  • Local rails only (SEPA / ACH / FPS / Interac) — no international fees.',
            '  • After sending the transfer, click “I HAVE SENT THE TRANSFER” to finish and go back to the website.','',
            'Thank you for your payment!',
        ].join('\n');
        const blob = new Blob([text], { type:'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href=url; a.download=`Invoice_${currentRef}.txt`;
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }

    const modal = document.getElementById('modal');
    const confirmRefInput = document.getElementById('confirmRefInput');
    const confirmRefMsg = document.getElementById('confirmRefMsg');

    document.getElementById('paidBtn').addEventListener('click', ()=>{
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        confirmRefInput.value='';
        confirmRefMsg.textContent='';
    });

    document.getElementById('closeModal').addEventListener('click', ()=>{
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });

    document.getElementById('submitModal').addEventListener('click', ()=>{
        const v = confirmRefInput.value.trim();
        const ok =
            v==currentRef &&
            document.getElementById('cb1').checked &&
            document.getElementById('cb4').checked &&
            document.getElementById('cb2').checked &&
            document.getElementById('cb3').checked;

        if(!ok){
            confirmRefMsg.textContent='Enter the digits exactly and tick all checkboxes.';
            confirmRefMsg.className='mt-1 text-[13px] text-rose-700';
            return;
        }
        showToast('Submitted ✓');
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        window.location.href = '{{ $redirectUrl }}';
    });

    function updateStepper(){
        step1.classList.toggle('done', refCopied);
        step1.classList.toggle('active', !refCopied);
        step2.classList.toggle('active', refCopied);
        step3.classList.toggle('active', refCopied);
        document.getElementById('paidBtn').disabled = !refCopied;
    }
    updateStepper();

    stickyClose.addEventListener('click', ()=>{
        stickyClosed = true;
        stickyRef.classList.remove('show');
    });

    const preferredRegion = "{{ $selectedRegion ?? 'EU' }}";
    const initialRegionKey = regions.some(r => r.key === preferredRegion)
        ? preferredRegion
        : (regions[0]?.key ?? null);

    if (initialRegionKey) {
        selectRegion(initialRegionKey);
    }
    window.addEventListener('DOMContentLoaded', ()=> {
        document.body.setAttribute('data-ready','1');
    });
</script>
</body>
</html>
