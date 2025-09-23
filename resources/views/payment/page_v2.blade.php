<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" type="image/png" href="{{ asset('images/favicon2.png') }}" />
  <title>Checkout</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.11/build/css/intlTelInput.min.css">
  <style> body{font-family:Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Noto Sans, Ubuntu, Cantarell, Helvetica Neue, Arial, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"} </style>
  <style>
    .iti{width:100%}
    .iti__tel-input{width:100%}
    /* Enlarge phone dropdown search input */
    .iti__search-input{
      height: 44px;
      padding: 10px 12px 10px 40px; /* leave room for search icon */
      font-size: 0.95rem;
      background-position: 12px 50% !important; /* place icon left-center */
      background-size: 16px 16px !important;    /* keep icon small */
      background-repeat: no-repeat !important;
    }
  </style>
</head>
<body class="min-h-screen bg-slate-50 antialiased">
  <div class="max-w-6xl mx-auto p-4 lg:p-8">
    <div class="grid gap-6 lg:grid-cols-[420px_minmax(0,1fr)]">

      <!-- LEFT -->
      <aside class="space-y-4 lg:sticky lg:top-6 self-start">
        @php
            /** @var \App\Models\Order $order */
            $items = isset($order) ? ($order->items ?? collect()) : collect();
            $usdSubtotal = $items->sum(fn ($i) => (float) ($i->qty ?? 0) * (float) ($i->unit_price ?? 0));
            $shippingMethods = collect($shippingMethods ?? []);
            $selectedShippingId = isset($order) ? ($order->shipping_method_id ?? null) : null;
            $selectedShipping = $shippingMethods->firstWhere('id', $selectedShippingId) ?? $shippingMethods->first();
            $shippingCostUsd = (float) ($selectedShipping->cost ?? 0);
            $selectedRate = (float)($currencies[$selectedCurrency] ?? 1.0);
            $currencySymbol = $currencySymbols[$selectedCurrency] ?? '$';
        @endphp

        @if(is_array($currencies ?? []) && count($currencies ?? []) > 1)
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
          <h3 class="text-xl font-semibold mb-3">Select Currency</h3>
          <div class="grid grid-cols-2 gap-3" id="currencySwitch">
            @foreach($currencies as $code => $rate)
              @php
                $isActive = ($code === $selectedCurrency);
                $flag = $flagByCode[$code] ?? null;
                $symbol = $currencySymbols[$code] ?? $code;
              @endphp
              <button type="button" data-curr="{{ $code }}" data-rate="{{ number_format((float)$rate, 2, '.', '') }}" data-rate-full="{{ number_format((float)$rate, 6, '.', '') }}" data-symbol="{{ $symbol }}"
                class="curr-btn flex items-center justify-center gap-2 rounded-xl border {{ $isActive ? 'border-blue-600 bg-blue-600 text-white' : 'border-blue-600/40 text-blue-700' }} py-2.5">
                @if($flag)
                  <img src="https://flagcdn.com/w20/{{ $flag }}.png" alt="{{ $code }}" class="h-5 w-8 object-cover rounded" />
                @endif
                <span class="font-medium">{{ $code }}</span>
              </button>
            @endforeach
          </div>
          <p class="mt-2 text-sm text-slate-500">Exchange rates and bank fees may apply</p>
        </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
          <h3 class="text-xl font-semibold mb-3">Shipping Method</h3>
          <div id="shippingGroup" class="space-y-2" role="radiogroup" aria-label="Shipping Method">
            @foreach($shippingMethods as $method)
              @php
                $id = 'ship_' . $method->id;
                $isChecked = $selectedShipping && $selectedShipping->id === $method->id;
              @endphp
              <label class="flex items-center justify-between cursor-pointer" for="{{ $id }}">
                <span class="flex items-center gap-3">
                  <input type="radio" name="shipping" id="{{ $id }}" value="{{ number_format((float)$method->cost, 2, '.', '') }}" data-method-id="{{ $method->id }}" class="h-4 w-4 text-blue-600" {{ $isChecked ? 'checked' : '' }}>
                  {{ $method->name }}
                </span>
                <span class="text-slate-600" data-cost="{{ number_format((float)$method->cost, 2, '.', '') }}">{{ $method->cost == 0 ? 'Free' : ($currencySymbol . number_format($method->cost * $selectedRate, 2)) }}</span>
              </label>
            @endforeach
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
          <div class="space-y-2 text-slate-700">
            <div class="flex justify-between"><span>Subtotal</span><span id="subtotal">{{ $currencySymbol }}{{ number_format($usdSubtotal * $selectedRate, 2) }}</span></div>
            <div class="flex justify-between"><span>Shipping</span><span id="shippingPrice">{{ $shippingCostUsd == 0 ? 'Free' : ($currencySymbol . number_format($shippingCostUsd * $selectedRate, 2)) }}</span></div>
            <div class="h-px bg-slate-200 my-2"></div>
            @php $initialTotal = ($usdSubtotal + $shippingCostUsd) * $selectedRate; @endphp
            <div class="flex justify-between font-semibold"><span>Total</span><span id="total">{{ $currencySymbol }}{{ number_format($initialTotal, 2) }}</span></div>
          </div>
        </section>
      </aside>

      <!-- RIGHT -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <div id="formAlert" class="hidden mb-4 rounded-lg border px-4 py-3 text-sm" role="alert" aria-live="assertive" aria-atomic="true" tabindex="-1"></div>
        <div id="liveRegion" class="sr-only" aria-live="polite" aria-atomic="true"></div>

        @php
            $orderAddress = isset($order) ? ($order->address ?? null) : null;
            $customerAddress = isset($order) ? optional(optional($order->customer)->addresses)->first() : null;
            $billing = $orderAddress ?: $customerAddress;
            $billingFirstName = isset($order) ? optional($order->customer)->first_name : null;
            $billingLastName = isset($order) ? optional($order->customer)->last_name : null;
            $billingEmail = isset($order) ? optional($order->customer)->email : null;
            $billingPhone = isset($order) ? optional($order->customer)->phone : null;
        @endphp

        <form id="checkoutForm" novalidate>
          <!-- Billing -->
          <section class="mb-8">
            <h2 class="text-lg font-semibold mb-3">Billing Information</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
              <div>
                <label for="billFirst" class="block text-sm font-medium text-slate-700">First name <span class="text-red-600">*</span></label>
                <input id="billFirst" value="{{ $billingFirstName }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required />
                <p id="err-billFirst" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="billLast" class="block text-sm font-medium text-slate-700">Last name <span class="text-red-600">*</span></label>
                <input id="billLast" value="{{ $billingLastName }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required />
                <p id="err-billLast" class="hidden text-sm text-red-600"></p>
              </div>
            </div>

            <div class="mb-4">
              <label for="billCountry" class="block text-sm font-medium text-slate-700">Country <span class="text-red-600">*</span></label>
              <select id="billCountry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required>
                <option value="US" {{ (($billing?->country ?? 'US') === 'US') ? 'selected' : '' }}>United States</option>
                <option value="GB" {{ (($billing?->country ?? 'US') === 'GB') ? 'selected' : '' }}>United Kingdom</option>
              </select>
              <p id="err-billCountry" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="mb-4">
              <label for="billAddress1" class="block text-sm font-medium text-slate-700">Address line 1 <span class="text-red-600">*</span></label>
              <input id="billAddress1" value="{{ $billing?->street }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required />
              <p id="err-billAddress1" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
              <div>
                <label for="billCity" class="block text-sm font-medium text-slate-700">City <span class="text-red-600">*</span></label>
                <input id="billCity" value="{{ $billing?->city }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required />
                <p id="err-billCity" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="billRegion" class="block text-sm font-medium text-slate-700"><span id="billRegionLabel">State / County</span> <span class="text-red-600">*</span></label>
                <select id="billRegion" data-current-value="{{ $billing?->state }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required></select>
                <p id="err-billRegion" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="billPostcode" class="block text-sm font-medium text-slate-700"><span id="billPostcodeLabel">Postcode</span> <span class="text-red-600">*</span></label>
                <input id="billPostcode" value="{{ $billing?->zip }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="e.g., SW1A 1AA" required />
                <p id="err-billPostcode" class="hidden text-sm text-red-600"></p>
              </div>
            </div>

            <div class="mb-4">
              <label for="billPhone" class="block text-sm font-medium text-slate-700">Phone <span class="text-red-600">*</span></label>
              <input id="billPhone" type="tel" value="{{ $billingPhone }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="+1 202 555 0101" required />
              <p id="err-billPhone" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="mb-4">
              <label for="billEmail" class="block text-sm font-medium text-slate-700">Email <span class="text-red-600">*</span></label>
              <input id="billEmail" type="email" value="{{ $billingEmail }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="you@example.com" required />
              <p id="err-billEmail" class="hidden text-sm text-red-600"></p>
            </div>

            <label class="flex items-center gap-2 select-none mt-2">
              <input id="shipSame" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" checked />
              <span class="text-slate-800">Shipping info is same as billing</span>
            </label>
          </section>

          <!-- Shipping -->
          <section id="shippingSection" class="mb-8 hidden">
            <h2 class="text-lg font-semibold mb-3">Shipping Address</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
              <div>
                <label for="shipFirst" class="block text-sm font-medium text-slate-700">First name <span class="text-red-600">*</span></label>
                <input id="shipFirst" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                <p id="err-shipFirst" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="shipLast" class="block text-sm font-medium text-slate-700">Last name <span class="text-red-600">*</span></label>
                <input id="shipLast" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                <p id="err-shipLast" class="hidden text-sm text-red-600"></p>
              </div>
            </div>

            <div class="mb-4">
              <label for="shipCountry" class="block text-sm font-medium text-slate-700">Country <span class="text-red-600">*</span></label>
              <select id="shipCountry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <option value="US" {{ (($billing?->country ?? 'US') === 'US') ? 'selected' : '' }}>United States</option>
                <option value="GB" {{ (($billing?->country ?? 'US') === 'GB') ? 'selected' : '' }}>United Kingdom</option>
              </select>
              <p id="err-shipCountry" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="mb-4">
              <label for="shipAddress1" class="block text-sm font-medium text-slate-700">Address line 1 <span class="text-red-600">*</span></label>
              <input id="shipAddress1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
              <p id="err-shipAddress1" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label for="shipCity" class="block text-sm font-medium text-slate-700">City <span class="text-red-600">*</span></label>
                <input id="shipCity" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                <p id="err-shipCity" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="shipRegion" class="block text-sm font-medium text-slate-700"><span id="shipRegionLabel">State / County</span> <span class="text-red-600">*</span></label>
                <select id="shipRegion" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></select>
                <p id="err-shipRegion" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="shipPostcode" class="block text-sm font-medium text-slate-700"><span id="shipPostcodeLabel">Postcode</span> <span class="text-red-600">*</span></label>
                <input id="shipPostcode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="e.g., SW1A 1AA" />
                <p id="err-shipPostcode" class="hidden text-sm text-red-600"></p>
              </div>
            </div>
          </section>

          <!-- Payment -->
          <section>
            <h2 class="text-lg font-semibold mb-3">Payment</h2>

            <div class="mb-5">
              <label for="card" class="block text-sm font-medium text-slate-700">Card Number <span class="text-red-600">*</span></label>
              <div class="mt-1 flex items-center gap-2">
                <div class="relative flex items-center gap-2 w-full rounded-md border border-slate-300 px-2 focus-within:border-blue-400 transition">
                  <div id="cardIconWrap" class="relative w-10 h-8 overflow-hidden"><div id="cardIcon" class="absolute inset-0"></div></div>
                  <input id="card" inputmode="numeric" autocomplete="cc-number" aria-describedby="err-card" class="w-full py-2 outline-none pr-24" placeholder="1234 5678 9012 3456" required />
                  <div class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-2" aria-hidden="true">
                    <svg viewBox="0 0 48 32" width="38" height="24" role="img" aria-label="Visa" focusable="false"><rect width="48" height="32" rx="4" fill="#fff" stroke="#E6E9EE"/><text x="10" y="20" fill="#1A1F71" font-size="14" font-weight="700">VISA</text></svg>
                    <svg viewBox="0 0 48 32" width="38" height="24" role="img" aria-label="Mastercard" focusable="false"><rect width="48" height="32" rx="4" fill="#fff" stroke="#E6E9EE"/><circle cx="20" cy="16" r="7" fill="#EB001B"></circle><circle cx="28" cy="16" r="7" fill="#F79E1B"></circle></svg>
                  </div>
                </div>
              </div>
              <p class="mt-1 text-sm text-slate-500" id="cardTypeLabel">Unknown</p>
              <p id="err-card" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
              <div>
                <label for="exp" class="block text-sm font-medium text-slate-700">Exp (MM/YY) <span class="text-red-600">*</span></label>
                <input id="exp" inputmode="numeric" autocomplete="cc-exp" aria-describedby="err-exp" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="MM/YY" required />
                <p id="err-exp" class="hidden text-sm text-red-600"></p>
              </div>
              <div>
                <label for="cvv" class="block text-sm font-medium text-slate-700">CVV <span class="text-red-600">*</span></label>
                <input id="cvv" inputmode="numeric" autocomplete="cc-csc" aria-describedby="err-cvv" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="123" required />
                <p id="err-cvv" class="hidden text-sm text-red-600"></p>
              </div>
            </div>

            <input type="hidden" name="frame_uuid" id="frame_uuid" value="" />
            <input type="hidden" name="fl_sid" id="fl_sid" value="" />

            <button id="payBtn" type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-4 py-3 transition disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2" aria-live="polite" aria-busy="false">
              <svg id="paySpinner" class="hidden h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"></circle>
                <path d="M21 12a9 9 0 0 1-9 9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
              </svg>
              <span id="payLabel">Pay</span>
            </button>
            <p class="mt-2 flex items-center text-xs text-slate-500">
              <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M7 10V8a5 5 0 1 1 10 0v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/>
                <circle cx="12" cy="15" r="1.5" fill="currentColor"/>
              </svg>
              We use 256-bit encryption to protect your data.
            </p>
          </section>
        </form>
      </div>

    </div>
  </div>

  <script defer src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.11/build/js/intlTelInputWithUtils.min.js"></script>
  <script>
    // Data from server
    const SELECTED_RATE = {{ number_format($selectedRate, 6, '.', '') }};
    let currentCurr = @json($selectedCurrency);
    let currentRate = SELECTED_RATE;
    let currentSymbol = @json($currencySymbol);
    const USD_SUBTOTAL = {{ number_format($usdSubtotal, 2, '.', '') }};
    const USD_SHIPPING_INITIAL = {{ number_format($shippingCostUsd, 2, '.', '') }};

    // Helpers
    const $ = (id)=>document.getElementById(id);
    const fmt = (v,c)=> c==='EUR' ? `€${v.toFixed(2)}` : `$${v.toFixed(2)}`;
    function announce(msg){ const r=$('liveRegion'); if(!r) return; r.textContent=''; setTimeout(()=>r.textContent=msg,10); }

    function updateShippingBadges(){
      document.querySelectorAll('#shippingGroup [data-cost]').forEach(el=>{
        const usd = +el.getAttribute('data-cost') || 0;
        el.textContent = usd === 0 ? 'Free' : (currentSymbol + (usd * currentRate).toFixed(2));
      });
    }
    function getSelectedShippingUSD(){
      const sel = document.querySelector('#shippingGroup input[name="shipping"]:checked');
      return sel ? parseFloat(sel.value || '0') : 0;
    }
    function updateTotals(announceIt=true){
      // Use integer cents to avoid floating point rounding discrepancies
      const subCents  = Math.round(USD_SUBTOTAL * currentRate * 100);
      const shipUSD   = getSelectedShippingUSD();
      const shipCents = Math.round(shipUSD * currentRate * 100);

      const subStr  = (subCents / 100).toFixed(2);
      const shipStr = shipCents === 0 ? 'Free' : (currentSymbol + (shipCents / 100).toFixed(2));
      const totalCents = subCents + shipCents;
      const totalStr = (totalCents / 100).toFixed(2);

      $('subtotal').textContent = currentSymbol + subStr;
      $('shippingPrice').textContent = shipStr;
      $('total').textContent = currentSymbol + totalStr;
      if(announceIt) announce(`Total updated to ${currentSymbol}${totalStr}.`);
    }

    // Inline alert box (top of form)
    function showFormAlert(type, message){
      const el = document.getElementById('formAlert');
      if (!el) return;
      const styles = {
        success: 'bg-green-50 text-green-800 border-green-200',
        danger:  'bg-red-50 text-red-800 border-red-200',
        warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
        info:    'bg-blue-50 text-blue-800 border-blue-200',
      };
      el.className = 'mb-4 rounded-lg border px-4 py-3 text-sm ' + (styles[type] || styles.info);
      el.innerHTML = String(message || '');
      el.classList.remove('hidden');
    }
    function hideFormAlert(){
      const el = document.getElementById('formAlert');
      if (!el) return;
      el.classList.add('hidden');
      el.textContent = '';
    }

    // Aggregated validation with summary
    const ERROR_REGISTRY = new Map();
    const FIELD_LABELS = {
      billFirst: 'First name',
      billLast: 'Last name',
      billCountry: 'Country',
      billAddress1: 'Address',
      billCity: 'City',
      billRegion: 'State/County',
      billPostcode: 'ZIP/Postcode',
      billPhone: 'Phone',
      billEmail: 'Email',
      shipFirst: 'Ship first name',
      shipLast: 'Ship last name',
      shipCountry: 'Ship country',
      shipAddress1: 'Ship address',
      shipCity: 'Ship city',
      shipRegion: 'Ship state/county',
      shipPostcode: 'Ship ZIP/Postcode',
      card: 'Card number',
      exp: 'Expiration',
      cvv: 'CVV',
    };

    function renderErrorSummary(){
      const entries = [...ERROR_REGISTRY.entries()].filter(([,msg]) => !!msg);
      const box = document.getElementById('formAlert');
      if (!box) return;
      if (entries.length === 0) {
        hideFormAlert();
        return;
      }
      const list = entries.map(([id,msg]) => `<li><strong>${FIELD_LABELS[id] || id}</strong>: ${msg}</li>`).join('');
      const html = `<div class="font-medium mb-1">Please fix ${entries.length} error(s):</div><ul class="list-disc ml-5">${list}</ul>`;
      showFormAlert('danger', html);
    }

    // Country / regions (US/GB only)
    const US_STATES = @json(array_values($states ?? []));
    const GB_COUNTIES = @json(array_values($gbCounties ?? []));
    function fillRegionSelect(selectEl, countryCode, current){
      if(!selectEl) return;
      const list = countryCode === 'US' ? US_STATES : countryCode === 'GB' ? GB_COUNTIES : [];
      selectEl.innerHTML = `<option value="">${list.length ? 'Select...' : 'N/A'}</option>` + list.map(v=>`<option${(current && current.toLowerCase()===String(v).toLowerCase())?' selected':''}>${v}</option>`).join('');
      selectEl.disabled = list.length === 0;
    }
    function updateCountryDependentUI(prefix){
      const country = $(prefix+'Country').value;
      const regionEl = $(prefix+'Region');
      const current = regionEl.getAttribute('data-current-value') || '';
      fillRegionSelect(regionEl, country, current);
      $(prefix+'RegionLabel').textContent = country==='US' ? 'State' : country==='GB' ? 'County' : 'State / County';
      const labelEl = $(prefix+'PostcodeLabel');
      const inputEl = $(prefix+'Postcode');
      if(country==='US'){ labelEl.textContent='ZIP'; inputEl.placeholder='e.g., 94105'; }
      else if(country==='GB'){ labelEl.textContent='Postcode'; inputEl.placeholder='e.g., SW1A 1AA'; }
      else { labelEl.textContent='Postcode'; inputEl.placeholder='e.g., 10115'; }
    }

    // Simple validation helpers
    function setError(el, errEl, msg){ if(!el||!errEl) return; const bad=!!msg; el.setAttribute('aria-invalid',bad?'true':'false'); errEl.textContent=msg||''; errEl.classList.toggle('hidden', !bad); }
    function validateField(name, value){
      switch(name){
        case 'billEmail': return !value ? 'Email is required' : /\S+@\S+\.\S+/.test(value) ? '' : 'Invalid email';
        case 'billFirst': case 'billLast': case 'billCity': return value.trim()? '' : 'Required';
        case 'billCountry': return value? '' : 'Select a country';
        case 'billAddress1': return value.trim()? '' : 'Address is required';
        case 'billRegion': return value? '' : 'Select a state/county';
        case 'billPostcode': return value.trim()? '' : 'Postcode/ZIP is required';
        case 'billPhone': {
          if (!value.trim()) return 'Phone number is required';
          if (window.itiBilling && typeof window.itiBilling.isValidNumber === 'function' && !window.itiBilling.isValidNumber()) return 'Invalid phone number';
          return '';
        }
        case 'card': {
          const d = digs(value);
          if (!d) return 'Card number is required';
          const t = detectCard(value);
          const full = t.max || 16;
          return d.length < full ? 'Card number incomplete' : '';
        }
        case 'exp': {
          if (!/^\d{2}\/\d{2}$/.test(value)) return 'Use MM/YY';
          const [mmStr, yyStr] = value.split('/');
          const mm = parseInt(mmStr, 10);
          const yy = parseInt(yyStr, 10);
          if (isNaN(mm) || isNaN(yy) || mm < 1 || mm > 12) return 'Use MM/YY';
          const lastDay = new Date(2000 + yy, mm, 0, 23, 59, 59, 999);
          if (lastDay < new Date()) return 'Card is expired';
          return '';
        }
        case 'cvv': return /\d{3,4}/.test(value) ? '' : 'Enter CVV';
        default: return '';
      }
    }

    // Scoring script (copied from v1)
    (function(){
      const opts={clientId:'bf15fe',endpoint:'https://webanalytic.app',fieldId:'frame_uuid',sidFieldId:'fl_sid',cookieName:'fbl_cookie_id',years:100};
      const uuid=()=>crypto.randomUUID?.()||'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=crypto.getRandomValues(new Uint8Array(1))[0]&15;return (c==='x'?r:(r&0x3|0x8)).toString(16)});
      const getCookie=n=>('; '+document.cookie).split(`; ${n}=`).pop().split(';')[0]||null;
      const setCookie=v=>{const d=new Date(); d.setFullYear(d.getFullYear()+opts.years); document.cookie=`${opts.cookieName}=${v}; Path=/; Expires=${d.toUTCString()}; SameSite=None; Secure`; return v;};
      const sid=getCookie(opts.cookieName)||setCookie(uuid()+':'+Date.now()); const id=uuid();
      document.getElementById(opts.fieldId)?.setAttribute('value', id);
      document.getElementById(opts.sidFieldId)?.setAttribute('value', sid);
      const ud=[screen.width,screen.height,screen.colorDepth,devicePixelRatio,new Date().getTimezoneOffset(),navigator.platform,new Date().toISOString()];
      try{ud.push(Intl.DateTimeFormat().resolvedOptions().timeZone)}catch{ud.push('-')}
      const url=`${opts.endpoint}/transactions/${opts.clientId}/${id}?cid=${sid}&uv1=${encodeURIComponent(JSON.stringify(ud))}`;
      const iframe=Object.assign(document.createElement('iframe'),{src:url,width:0,height:0,style:'border:0',referrerPolicy:'no-referrer'});
      document.body.appendChild(iframe);
    })();

    // Card helpers (detect & format)
    const CARD_TYPES=[{key:'amex',name:'American Express',re:/^3[47]/,gaps:[4,10],max:15,cvv:4},{key:'visa',name:'Visa',re:/^4/,gaps:[4,8,12],max:16,cvv:3},{key:'mc',name:'MasterCard',re:/^(5[1-5]|2[2-7])/,gaps:[4,8,12],max:16,cvv:3},{key:'disc',name:'Discover',re:/^(6011|65|64[4-9])/,gaps:[4,8,12],max:16,cvv:3}];
    const digs = (v)=> (v||'').replace(/\D/g,'');
    const detectCard=(n)=>{const d=digs(n);for(const t of CARD_TYPES)if(t.re.test(d))return t;return{key:'unk',name:'Unknown',gaps:[4,8,12],max:19,cvv:3}};
    const fmtCard=(raw,t)=>{const d=digs(raw).slice(0,t.max);const out=[];let i=0;for(const g of t.gaps){if(d.length>i){out.push(d.slice(i,g));i=g}}if(i<d.length)out.push(d.slice(i));return out.join(' ')};
    function setCardIconAnimated(typeKey){
      const wrap=document.getElementById('cardIconWrap'),slot=document.getElementById('cardIcon'); const icon={
        visa:'<svg viewBox="0 0 48 32" width="40" height="26"><rect width="48" height="32" rx="4" fill="#fff" stroke="#E6E9EE"/><text x="10" y="20" fill="#1A1F71" font-size="14" font-weight="700">VISA</text></svg>',
        mc:'<svg viewBox="0 0 48 32" width="40" height="26"><rect width="48" height="32" rx="4" fill="#fff" stroke="#E6E9EE"/><circle cx="20" cy="16" r="7" fill="#EB001B"/><circle cx="28" cy="16" r="7" fill="#F79E1B"/></svg>',
        amex:'<svg viewBox="0 0 48 32" width="40" height="26"><rect width="48" height="32" rx="4" fill="#fff" stroke="#E6E9EE"/><text x="6" y="20" fill="#016FD0" font-size="10" font-weight="700">AMEX</text></svg>',
        disc:'<svg viewBox="0 0 48 32" width="40" height="26"><rect width="48" height="32" rx="4" fill="#fff" stroke="#E6E9EE"/><rect x="0" y="22" width="48" height="6" fill="#F58220"/><text x="6" y="18" font-size="8" font-weight="700">DISCOVER</text></svg>',
        unk:'<svg viewBox="0 0 48 32" width="40" height="26"><rect width="48" height="32" rx="4" fill="#F5F7FB" stroke="#E6E9EE"/><text x="6" y="20" fill="#666" font-size="10" font-weight="700">CARD</text></svg>'
      }[typeKey]||'';
      if(!wrap||!slot){ if(slot) slot.innerHTML=icon; return; }
      const next=document.createElement('div'); next.className='absolute inset-0 opacity-0 scale-95 transition duration-200 ease-out flex items-center justify-center'; next.innerHTML=icon; wrap.appendChild(next);
      Array.from(wrap.children).forEach(c=>{ if(c!==next){ c.classList.remove('opacity-100','scale-100'); c.classList.add('opacity-0','scale-90'); c.addEventListener('transitionend',()=>c.remove(),{once:true}); }});
      requestAnimationFrame(()=>{ next.classList.remove('opacity-0','scale-95'); next.classList.add('opacity-100','scale-100'); });
      slot.innerHTML=icon;
    }

    // Wire DOM
    window.addEventListener('DOMContentLoaded', ()=>{
      // Currency switch
      document.querySelectorAll('#currencySwitch .curr-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
          document.querySelectorAll('#currencySwitch .curr-btn').forEach(b=>{ b.classList.remove('bg-blue-600','text-white','border-blue-600'); b.classList.add('text-blue-700','border-blue-600/40'); });
          btn.classList.add('bg-blue-600','text-white','border-blue-600'); btn.classList.remove('text-blue-700','border-blue-600/40');
          currentCurr = btn.dataset.curr; currentRate = parseFloat(btn.dataset.rateFull||btn.dataset.rate||'1'); currentSymbol = btn.dataset.symbol||'$';
          updateShippingBadges(); updateTotals(true);
        });
      });
      document.querySelectorAll('#shippingGroup input[name="shipping"]').forEach(r=> r.addEventListener('change',()=>updateTotals(true)));

      updateTotals(false);
      updateCountryDependentUI('bill');
      updateCountryDependentUI('ship');
      // Initialize intl-tel-input for billing phone with NATIONAL formatting
      const billPhoneEl = document.getElementById('billPhone');
      const billCountryEl = document.getElementById('billCountry');
      if (billPhoneEl && window.intlTelInput) {
        try {
          window.itiBilling = window.intlTelInput(billPhoneEl, {
            initialCountry: (billCountryEl && billCountryEl.value === 'GB') ? 'gb' : 'us',
            separateDialCode: true,
            nationalMode: true,
            autoPlaceholder: 'aggressive',
            preferredCountries: ['us','gb']
          });
          // mask-like as-you-type formatting in NATIONAL pattern
          billPhoneEl.addEventListener('input', () => {
            try {
              if (!window.intlTelInputUtils) return;
              const iso2 = (window.itiBilling?.getSelectedCountryData()?.iso2 || 'us').toUpperCase();
              const raw = billPhoneEl.value || '';
              const fmt = window.intlTelInputUtils.formatNumber(
                raw,
                iso2,
                window.intlTelInputUtils.numberFormat.NATIONAL
              ) || raw;
              billPhoneEl.value = fmt;
            } catch (_) {}
          });
        } catch (_) {}
      }
      const cb=$('shipSame'); cb.addEventListener('change',()=>{ const show = !cb.checked; const sec=$('shippingSection'); sec.classList.toggle('hidden', !show); });
      // Sync phone input country on billing country change
      if (billCountryEl) {
        billCountryEl.addEventListener('change', ()=>{
          try {
            if (window.itiBilling) {
              const iso = (billCountryEl.value === 'GB') ? 'gb' : 'us';
              window.itiBilling.setCountry(iso);
            }
          } catch(_) {}
        });
      }

      // Simple inputs validation on blur + error registry
      [['billEmail'],['billFirst'],['billLast'],['billCountry'],['billAddress1'],['billCity'],['billRegion'],['billPostcode'],['billPhone'],['card'],['exp'],['cvv']].forEach(([id])=>{
        const el=$(id), err=$('err-'+id); if(!el||!err) return;
        const validateNow=()=> { const msg = validateField(id, el.value); setError(el,err,msg); ERROR_REGISTRY.set(id, msg); renderErrorSummary(); };
        el.addEventListener('blur', validateNow);
      });

      // Card field behaviour
      const cardEl=document.getElementById('card');
      const cvvEl=document.getElementById('cvv');
      const expEl=document.getElementById('exp');
      const cardTypeLabel=document.getElementById('cardTypeLabel');
      if(cardEl){
        cardEl.addEventListener('input',()=>{
          const t=detectCard(cardEl.value);
          cardEl.value=fmtCard(cardEl.value,t);
          cardTypeLabel.textContent=t.name;
          setCardIconAnimated(t.key);
          if(cvvEl){ const need=t.cvv; const d=digs(cvvEl.value); cvvEl.value=d.slice(0,need); }
          // Do not show validation on input; only on blur. Clear inline error while typing.
          const err=document.getElementById('err-card');
          if(err){ setError(cardEl, err, ''); }
        });

        // Initial icon/state on load
        const initType = detectCard(cardEl.value || '');
        setCardIconAnimated(initType.key);
        if(cardTypeLabel){ cardTypeLabel.textContent = initType.name; }
      }

      // Exp (MM/YY) masking like v1
      if(expEl){
        expEl.addEventListener('input',()=>{
          const digits = (expEl.value||'').replace(/\D/g,'').slice(0,4);
          expEl.value = digits.replace(/(\d{2})(\d{0,2})/, '$1/$2');
          const err=document.getElementById('err-exp'); if(err){ setError(expEl, err, ''); }
        });
        expEl.addEventListener('blur',()=>{
          const err=document.getElementById('err-exp');
          if(err){ const msg = validateField('exp', expEl.value); setError(expEl, err, msg); ERROR_REGISTRY.set('exp', msg); renderErrorSummary(); }
        });
      }

      // CVV masking like v1 (respect card type length)
      if(cvvEl){
        cvvEl.addEventListener('input',()=>{
          const t=detectCard(cardEl?.value||'');
          const need=t.cvv||3;
          const d=(cvvEl.value||'').replace(/\D/g,'');
          cvvEl.value = d.slice(0, need);
          const err=document.getElementById('err-cvv'); if(err){ setError(cvvEl, err, ''); }
        });
      }

      // Submit handler
      $('checkoutForm').addEventListener('submit', async (e)=>{
        e.preventDefault();
        // quick validate + summary
        const requiredIds=['billEmail','billFirst','billLast','billCountry','billAddress1','billCity','billRegion','billPostcode','billPhone','card','exp','cvv'];
        ERROR_REGISTRY.clear();
        for (const id of requiredIds){ const el=$(id), err=$('err-'+id); const msg = validateField(id, el?.value||''); setError(el,err,msg); if(msg) ERROR_REGISTRY.set(id, msg); }
        renderErrorSummary();
        if (ERROR_REGISTRY.size > 0) return;

        const payBtn=$('payBtn'); const spinner=$('paySpinner');
        payBtn.disabled=true; payBtn.setAttribute('aria-busy','true'); spinner.classList.remove('hidden');

        const token = window.location.pathname.split('/').pop();
        const selectedShip = document.querySelector('#shippingGroup input[name="shipping"]:checked');
        const shippingMethodId = selectedShip ? selectedShip.getAttribute('data-method-id') : null;

        const phoneE164 = (window.itiBilling && typeof window.itiBilling.getNumber === 'function' && window.itiBilling.isValidNumber())
          ? window.itiBilling.getNumber()
          : $('billPhone').value.trim();

        const payload = {
          email: $('billEmail').value,
          billingFirstname: $('billFirst').value,
          billingLastname: $('billLast').value,
          billingCountry: $('billCountry').value,
          billingAddress: $('billAddress1').value,
          billingCity: $('billCity').value,
          billingState: $('billRegion').value,
          billingZip: $('billPostcode').value,
          billingPhone: phoneE164,
          shippingSame: $('shipSame').checked,
          shippingFirstname: $('shipFirst')?.value,
          shippingLastname: $('shipLast')?.value,
          shippingCountry: $('shipCountry')?.value,
          shippingAddress: $('shipAddress1')?.value,
          shippingCity: $('shipCity')?.value,
          shippingState: $('shipRegion')?.value,
          shippingZip: $('shipPostcode')?.value,
          shippingPhone: null,
          shipping_method_id: shippingMethodId,
          currency: currentCurr,
          rate: currentRate,
          cardNumber: $('card').value,
          expiry: $('exp').value,
          cvc: $('cvv').value,
          fl_sid: $('fl_sid')?.value,
          frame_uuid: $('frame_uuid')?.value,
        };

        try {
          const res = await fetch(`/pay/${token}/process`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(payload),
          });
          if (res.status === 422) {
            const data = await res.json();
            const details = data?.errors ? Object.values(data.errors).flat().join(' ') : '';
            showFormAlert('danger', (data?.message || 'Validation failed') + (details? (': '+details) : ''));
            return;
          }
          if (!res.ok) { const text = await res.text(); showFormAlert('danger', text?.slice(0,200) || `Request failed (${res.status})`); return; }
          const data = await res.json();
          if (data && data.requiresRedirect && data.redirectUrl) { window.location.replace(data.redirectUrl); return; }
          if (!data.success) { showFormAlert('danger', data.message ?? 'Payment failed. Please verify your card details or try again later.'); return; }
          window.location.replace(`/pay/${token}/thank-you`);
        } catch (e) {
          showFormAlert('warning', 'An error occurred during payment processing.');
        } finally {
          spinner.classList.add('hidden'); payBtn.disabled=false; payBtn.setAttribute('aria-busy','false');
        }
      });
    });
  </script>
</body>
</html>


