<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Local Payment — EU / UK / AU / US / CA</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon2.png') }}" />
    <script src="https://cdn.tailwindcss.com"></script>
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
      .toast{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#0b7;color:#fff;padding:.55rem .8rem;border-radius:.7rem;font-weight:600;box-shadow:0 10px 24px -16px rgba(2,6,23,.4);opacity:0;transition:opacity .18s}
      .toast.show{opacity:1}
      body[data-ready="0"] .app{opacity:0}
      body[data-ready="1"] .app{opacity:1;transition:opacity .12s}
      .focus-ring:focus{outline:none;box-shadow:0 0 0 3px var(--ring-blue)}
      .need-ref-first{border-color:#93c5fd}
      @keyframes softPulse{0%{box-shadow:0 0 0 0 rgba(59,130,246,.45)}70%{box-shadow:0 0 0 10px rgba(59,130,246,0)}100%{box-shadow:0 0 0 0 rgba(59,130,246,0)}}
      .ref-pulse{animation:softPulse 1.6s ease-in-out infinite;border-color:#93c5fd!important}
    </style>
</head>
<body class="bg-slate-50 text-slate-900" data-ready="0">
<!-- icons -->
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
</svg>

<div class="app mx-auto max-w-3xl p-4">
  <div class="mb-3 flex items-center gap-2.5">
    <div class="h-9 w-9 rounded-full bg-white shadow ring-1 ring-slate-200 flex items-center justify-center text-lg">💳</div>
    <div>
      <h1 class="text-lg font-semibold flex items-center gap-2">Local Payment <span class="text-slate-400">(EU / UK / AU / US / CA)</span></h1>
      <p class="text-[13px] text-slate-500 leading-5">Pay easily via SEPA, ACH, FPS, Interac or local bank transfer.</p>
    </div>
  </div>

  <div id="flags" class="mb-4 grid grid-cols-3 sm:grid-cols-5 gap-2"></div>

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
        <div id="amountValue" class="mt-1 mono text-emerald-900 text-xl font-extrabold">EUR&nbsp;0.00</div>
      </div>
      <button id="copyAmount" class="btn border-emerald-300 text-emerald-700 shrink-0"><svg width="14" height="14"><use href="#i-copy"/></svg> Copy</button>
    </div>

    <div id="refBox" class="mt-2.5 rounded-2xl border border-blue-200 bg-blue-50 p-3">
      <div class="mb-1 text-[10px] uppercase tracking-wide text-blue-700 font-semibold">Reference (Invoice Number)</div>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex-1 min-w-0">
          <div id="referenceValue" class="value-scroll mono font-extrabold text-blue-900 text-xl break-words" title=""></div>
        </div>
        <div class="flex items-center gap-2">
          <button id="copyRef" class="btn border-blue-300 text-blue-700 focus-ring shrink-0"><svg width="14" height="14"><use href="#i-copy"/></svg> Copy</button>
        </div>
      </div>
      <p class="mt-1.5 text-[13px] text-blue-800 leading-5"><strong>Important:</strong> enter <u>only</u> this number in the payment reference — no extra symbols or words.</p>
      <p class="text-[12px] text-blue-700 mt-1">Example: <span class="mono">123456</span> — correct; <span class="mono">INV-123456</span> — incorrect.</p>
    </div>

    <div id="fields" class="mt-3 grid gap-2.5"></div>

    <div class="mt-3 rounded-xl bg-amber-50 p-2.5 text-[13px] text-amber-800 leading-5">
      👉 Auto-match with correct reference • 🔒 Local banking network • ⚡ Most payments arrive instantly
    </div>

    <div class="mt-3.5 flex flex-wrap items-center justify-end gap-1.5">
      <button id="downloadInvoice" class="btn shrink-0"><svg width="14" height="14"><use href="#i-download"/></svg> Download .txt</button>
      <button id="printInvoice" class="btn shrink-0"><svg width="14" height="14"><use href="#i-print"/></svg> Print / Save PDF</button>
      <button id="paidBtn" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-1.5 text-[13px] font-semibold text-white shadow hover:bg-blue-700 active:scale-[.98] focus-ring shrink-0">
        <svg width="14" height="14"><use href="#i-check"/></svg> I HAVE PAID
      </button>
    </div>
  </div>
</div>

<div id="modal" class="fixed inset-0 z-40 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative z-10 w-[92%] max-w-md rounded-2xl bg-white p-4 shadow-xl border border-slate-200">
    <h3 class="text-base font-semibold mb-2">Quick confirmation</h3>
    <ul class="text-sm text-slate-700 space-y-2 mb-3">
      <li><label class="inline-flex items-start gap-2"><input type="checkbox" class="mt-1"> <span>I used <strong>only</strong> the invoice number as memo/reference</span></label></li>
      <li><label class="inline-flex items-start gap-2"><input type="checkbox" class="mt-1"> <span>Amount & currency are correct</span></label></li>
      <li><label class="inline-flex items-start gap-2"><input type="checkbox" class="mt-1"> <span>I saved a receipt from my bank</span></label></li>
    </ul>
    <div class="flex justify-end gap-2">
      <button id="closeModal" class="btn">Close</button>
      <button id="submitModal" class="btn" style="border-color:#60a5fa;color:#1d4ed8">Submit</button>
    </div>
  </div>
</div>

<div id="toast" class="toast" role="status" aria-live="polite">Copied ✓</div>

<script>
  // Dynamic overrides from backend
  const data = {
    orderId: @json(optional($order)->id),
    currency: @json(optional($order)->currency ?? 'EUR'),
    total: (Number({{ number_format(optional($order)->total_price ?? 0, 2, '.', '') }}) || 0) * (Number({{ number_format(optional($order)->rate ?? 1, 6, '.', '') }}) || 1),
  };

  // Regions dataset (defaults), EU values overridden from PayEasy
  const backend = @json($airwallex ?? []);
  const backendRef = (Array.isArray(backend) ? backend.reference : (backend && backend.reference)) || '';

  console.log('BACKEND', backend);
  console.log('BACKEND REF', backendRef);

  function toDetails(input){
    if (Array.isArray(input)) return input;
    const out = [];
    if (!input || typeof input !== 'object') return out;
    const map = {
      beneficiary: 'Beneficiary', recipient: 'Beneficiary',
      iban: 'IBAN', bic: 'BIC/SWIFT', swift: 'BIC/SWIFT', bank: 'Bank',
      account: 'Account', routing_ach: 'Routing (ACH)', routing_wire: 'Routing (Wire)',
      sort_code: 'Sort code', bsb: 'BSB', transit: 'Transit', institution: 'Institution', interac: 'Interac (email)'
    };
    for (const [k,v] of Object.entries(input)){
      const label = map[k];
      if (label && (v ?? '') !== '') out.push({ k: label, v: String(v), icon: undefined });
    }
    return out;
  }

  function parseAmount(val){ return Number(String(val||'').replace(/[^0-9.]/g,'')) || 0; }
  function canonRegion(r){
    const details = Array.isArray(r?.details) ? r.details : toDetails(r);
    const amountBase = parseAmount(r?.amount || r?.amountBase);
    return {
      key: String(r?.key || r?.code || 'EU').toUpperCase(),
      flag: r?.flag || '',
      label: r?.label || String(r?.key || r?.code || 'Region'),
      currency: r?.currency || '',
      subtitle: r?.subtitle || '',
      details,
      amountBase: amountBase || (String((r?.currency||'').toUpperCase())==='EUR' ? (Number(data.total)||0) : 0),
    };
  }

  function normalizeRegions(b){
    if (Array.isArray(b)) return b.map(canonRegion);
    if (b && Array.isArray(b.regions)) return b.regions.map(canonRegion);
    if (b && typeof b === 'object') {
      const keys = Object.keys(b || {});
      const numericKeys = keys.filter(k => /^\d+$/.test(k));
      if (numericKeys.length) {
        return numericKeys.sort((a,b)=>Number(a)-Number(b)).map(k => canonRegion(b[k]));
      }
      return [canonRegion(b)];
    }
    return [];
  }

  const regions = normalizeRegions(backend);

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
  let current = null; // currently selected region

  function makeRefNumber(){ return (data.orderId ? ('OR-' + data.orderId) : Math.floor(Math.random()*1e6).toString().padStart(6,'0')); }
  function showToast(msg){ toast.textContent=msg; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'),1200); }
  async function copy(text){
    const str = String(text ?? '');
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(str);
      } else {
        throw new Error('insecure');
      }
    } catch (_) {
      try {
        const ta = document.createElement('textarea');
        ta.value = str;
        ta.style.position = 'fixed';
        ta.style.left = '-1000px';
        ta.style.top = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        document.execCommand && document.execCommand('copy');
        document.body.removeChild(ta);
      } catch (_) {}
    }
    showToast('Copied ✓');
  }
  let refCopied=false;

  function isMono(label){ return /(IBAN|Account|Routing|BIC|SWIFT|Sort code|BSB|Transit|Institution|Interac)/i.test(label); }

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
        <button class="btn shrink-0 ml-2 copy-btn ${refCopied ? '' : 'need-ref-first'}" data-copy="${value}">
          <svg width="14" height="14"><use href="#i-copy"/></svg> Copy
        </button>
      </div>
    `;
    const btn = row.querySelector('.copy-btn');
    btn.addEventListener('click', async ()=>{
      await copy(btn.dataset.copy);
      btn.innerHTML = `<svg width="14" height="14"><use href="#i-check"/></svg> Copied`;
      btn.classList.remove('need-ref-first');
      setTimeout(()=> btn.innerHTML = `<svg width="14" height="14"><use href="#i-copy"/></svg> Copy`, 1100);
    });
    return row;
  }

  function renderAmount(r){ amountEl.textContent = `${r.currency}\u00A0${r.amountBase.toFixed(2)}`; }

  function renderRegion(r){
    current = r;
    labelEl.textContent = r.label;
    subtitleEl.textContent = r.subtitle;
    currencyEl.textContent = `Currency: ${r.currency}`;
    renderAmount(r);
    fieldsEl.innerHTML = '';
    r.details.forEach(d => fieldsEl.appendChild(fieldRow(d.k, d.v, d.icon)));
    document.querySelectorAll('.copy-btn').forEach(b => { if(!refCopied) b.classList.add('need-ref-first'); });
  }

  // build flags
  regions.forEach(r=>{
    const b=document.createElement('button');
    b.dataset.key=r.key;
    b.className='flex flex-col items-center rounded-2xl border px-3 py-1.5 text-center transition border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    b.innerHTML=`<span class="text-xl mb-0.5" aria-hidden="true">${r.flag||'🏳️'}</span><span class="text-[11px] font-medium leading-4">${r.key}</span>`;
    b.addEventListener('click',()=> selectRegion(r.key));
    flagsRoot.appendChild(b);
  });

  // reference
  const defaultRef = backendRef ? String(backendRef) : (data.orderId ? ('OR-' + data.orderId) : makeRefNumber());
  let currentRef = defaultRef;
  function setRef(n){ currentRef=n; refEl.textContent=n; refEl.title=n; }
  setRef(currentRef);

  function pulseRefBox(ms=4000){
    refBox.classList.add('ref-pulse');
    setTimeout(()=>refBox.classList.remove('ref-pulse'), ms);
  }
  pulseRefBox(4000);

  copyRefBtn.addEventListener('click', async ()=>{
    await copy(currentRef);
    refCopied=true;
    document.querySelectorAll('.copy-btn').forEach(b=>b.classList.remove('need-ref-first'));
    copyRefBtn.innerHTML=`<svg width="14" height="14"><use href="#i-check"/></svg> Copied`;
    setTimeout(()=> copyRefBtn.innerHTML=`<svg width="14" height="14"><use href="#i-copy"/></svg> Copy`,1100);
  });

  copyAmountBtn.addEventListener('click', async ()=>{
    await copy(amountEl.textContent.replace(/\u00A0/g,' '));
    copyAmountBtn.innerHTML=`<svg width="14" height="14"><use href="#i-check"/></svg> Copied`;
    setTimeout(()=> copyAmountBtn.innerHTML=`<svg width="14" height="14"><use href="#i-copy"/></svg> Copy`,1100);
  });

  document.getElementById('downloadInvoice').addEventListener('click', ()=>{
    const r = current || regions[0];
    const lines = (r?.details||[]).map(d => `${d.k}: ${d.v}`);
    const ts = new Date().toISOString().replace('T',' ').slice(0,19);
    const displayAmount = `${r.currency} ${r.amountBase.toFixed(2)}`;
    const PayeasyBank = { reference: backendRef || currentRef };
    const text = [
      '=== PAYMENT INVOICE ===',
      `Issued at: ${ts}`, '',
      'Merchant:','  REXPRESS S.R.L.','',
      'Payment Method:',`  Local Bank Transfer — ${r.label}`,`  Currency: ${r.currency}`,`  Amount: ${displayAmount}`,'',
      'Reference (Invoice Number):',`  ${PayeasyBank.reference}`,'',
      'Bank Details:',...lines.map(l=>'  '+l),'',
      'Reference rule:','  Use ONLY the invoice number as the payment reference (memo).',
      '  Any extra text may delay or prevent automatic matching.','',
      'Notes:','  • Local rails only (SEPA / ACH / FPS / Interact) — no international fees.',
      '  • After sending the transfer, click “I HAVE PAID” to speed up verification.','',
      'Thank you for your payment!',
    ].join('\n');
    const blob = new Blob([text], { type:'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download=`Invoice_${PayeasyBank.reference}.txt`;
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });
  document.getElementById('printInvoice').addEventListener('click', ()=> window.print());

  // modal
  const modal = document.getElementById('modal');
  document.getElementById('paidBtn').addEventListener('click', ()=>{
    window.location.href = @json(route('payment.thanks', ['token' => $token ?? '', 'pm' => 'airwallex']));
  });
  document.getElementById('closeModal').addEventListener('click', ()=>{
    modal.classList.add('hidden'); modal.classList.remove('flex');
  });
  document.getElementById('submitModal').addEventListener('click', ()=>{
    window.location.href = @json(route('payment.thanks', ['token' => $token ?? '', 'pm' => 'airwallex']));
  });

  function selectRegion(key){
    const r = regions.find(x=>x.key===key)||regions[0];
    [...flagsRoot.children].forEach(btn=>{
      const act = btn.dataset.key===r.key;
      btn.className = `flex flex-col items-center rounded-2xl border px-3 py-1.5 text-center transition ${act?'border-blue-400 bg-blue-50 text-blue-700 shadow':'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`;
    });
    renderRegion(r);
  }

  // init default region from backend (fallback to EU)
  const defaultRegion = @json($selectedRegion ?? 'EU');
  const availableKeys = regions.map(r=>r.key);
  const pick = availableKeys.includes(defaultRegion) ? defaultRegion : (availableKeys[0] || 'EU');
  selectRegion(pick);
  document.addEventListener('DOMContentLoaded', ()=> { document.body.setAttribute('data-ready','1'); });
</script>
</body>
</html>


