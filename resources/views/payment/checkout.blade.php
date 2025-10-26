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
        /* Valid UI: green underline + check icon */
        .valid-underline { box-shadow: inset 0 -2px 0 #16a34a !important; }
        .valid-check{
            background-repeat: no-repeat;
            background-position: right .6rem center;
            background-size: 18px 18px;
            background-image: url("data:image/svg+xml,%3Csvg width='18' height='18' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='10' cy='10' r='9' stroke='%2316a34a' stroke-width='2'/%3E%3Cpath d='M6 10.5l3 3 5-6' stroke='%2316a34a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            padding-right: 2rem !important;
        }
        /* Tiny reference illustrations for Exp and CVV fields */
        .with-exp-icon{
            background-image: url("data:image/svg+xml,%3Csvg width='34' height='22' viewBox='0 0 48 32' xmlns='http://www.w3.org/2000/svg'%3E%3Crect x='1' y='1' width='46' height='30' rx='4' fill='%23fff' stroke='%23E6E9EE'/%3E%3Crect x='6' y='10' width='36' height='6' fill='%23EAF0FB'/%3E%3Crect x='28' y='20' width='14' height='4' fill='%232563EB'/%3E%3Ctext x='29' y='23' font-size='5' fill='%23fff' font-family='Arial' %3EEXP%3C/text%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .55rem center; background-size: 34px 22px;
            padding-right: 2.3rem !important;
        }
        .with-cvv-icon{
            background-image: url("data:image/svg+xml,%3Csvg width='34' height='22' viewBox='0 0 48 32' xmlns='http://www.w3.org/2000/svg'%3E%3Crect x='1' y='1' width='46' height='30' rx='4' fill='%23fff' stroke='%23E6E9EE'/%3E%3Crect x='1' y='6' width='46' height='6' fill='%23262626'/%3E%3Crect x='28' y='18' width='14' height='6' fill='%23EAF0FB' stroke='%2316a34a'/%3E%3Ctext x='31' y='22' font-size='5' fill='%23262626' font-family='Arial'%3ECVV%3C/text%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .55rem center; background-size: 34px 22px;
            padding-right: 2.3rem !important;
        }
        /* Footer note show/hide */
        .footer-note{ transition: opacity .18s ease, transform .18s ease; }
        .footer-note.hidden-note{ opacity:0; transform:translateY(8px); visibility:hidden; pointer-events:none; }
        .footer-note.visible-note{ opacity:1; transform:translateY(0); visibility:visible; pointer-events:auto; }
        /* Soft pulse animation for urgency */
        @keyframes softPulse { 0%{opacity:.85} 50%{opacity:1} 100%{opacity:.85} }
        .pulse-soft { animation: softPulse 1.2s ease-in-out infinite; }
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

            @if(($items->count() ?? 0) > 0)
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
                <h3 class="text-xl font-semibold mb-3">Items</h3>
                <ul class="space-y-2">
                    @foreach($items as $it)
                        @php
                            $qty = (int) ($it->qty ?? 1);
                            $unitUsd = (float) ($it->unit_price ?? 0);
                            $lineUsd = $qty * $unitUsd;
                            $title = optional($it->product)->name ?? optional($it->product)->title ?? ($it->name ?? 'Item');
                        @endphp
                        <li class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-slate-800">{{ $title }} x {{ $qty }}</div>
                            </div>
                            <div class="text-slate-800 font-medium whitespace-nowrap">
                                <span class="item-line-amount" data-line-usd="{{ number_format($lineUsd, 2, '.', '') }}">{{ $currencySymbol }}{{ number_format($lineUsd * $selectedRate, 2) }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
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

            <!-- Left Status (Zelle) — shows timer/thank you -->
            <section id="leftStatusCard" class="hidden rounded-2xl border border-purple-200 bg-white shadow-sm p-4">
                <div class="flex items-start gap-3">
                    <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="4" fill="#7C3AED"/>
                        <path d="M8 6h8l-7 12h7" stroke="#fff" stroke-width="2" stroke-linejoin="round" fill="none"/>
                    </svg>
                    <div class="min-w-0">
                        <div class="text-slate-900 font-semibold">Zelle® payment</div>
                        <div id="leftStatusDynamic" class="mt-1 text-sm text-purple-800"></div>
                    </div>
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
                            <option value="AU" {{ (($billing?->country ?? 'US') === 'AU') ? 'selected' : '' }}>Australia</option>
                            <option value="FR" {{ (($billing?->country ?? 'US') === 'FR') ? 'selected' : '' }}>France</option>
                            <option value="DE" {{ (($billing?->country ?? 'US') === 'DE') ? 'selected' : '' }}>Germany</option>
                        </select>
                        <p id="err-billCountry" class="hidden text-sm text-red-600"></p>
                    </div>

                    <div class="mb-4">
                        <label for="billAddress1" class="block text-sm font-medium text-slate-700">Address line <span class="text-red-600">*</span></label>
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
                            <option value="AU" {{ (($billing?->country ?? 'US') === 'AU') ? 'selected' : '' }}>Australia</option>
                            <option value="FR" {{ (($billing?->country ?? 'US') === 'FR') ? 'selected' : '' }}>France</option>
                            <option value="DE" {{ (($billing?->country ?? 'US') === 'DE') ? 'selected' : '' }}>Germany</option>
                        </select>
                        <p id="err-shipCountry" class="hidden text-sm text-red-600"></p>
                    </div>

                    <div class="mb-4">
                        <label for="shipAddress1" class="block text-sm font-medium text-slate-700">Address line<span class="text-red-600">*</span></label>
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
                    <h2 class="text-lg font-semibold mb-1 flex items-center gap-2">
                        Payment
                        <span class="text-xs rounded-full bg-slate-100 text-slate-700 px-2 py-0.5">Choose a method</span>
                    </h2>

                    <!-- Payment method toggle -->
                    <div class="mb-4 grid grid-cols-2 gap-2" role="tablist" aria-label="Payment method">
                        <button id="pmCardBtn" type="button" role="tab"
                                class="pm-btn flex items-center justify-center gap-2 rounded-lg border border-blue-600 bg-blue-600 text-white py-2.5"
                                data-method="card" aria-selected="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="9" width="18" height="3" fill="currentColor"/></svg>
                            <span class="font-medium">Card (Visa / MC)</span>
                        </button>
                        <button id="pmZelleBtn" type="button" role="tab"
                                class="pm-btn flex items-center justify-center gap-2 rounded-lg border border-purple-600/40 text-purple-700 py-2.5"
                                data-method="zelle" aria-selected="false">
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4" fill="#7C3AED"/><path d="M8 6h8l-7 12h7" stroke="#fff" stroke-width="2" stroke-linejoin="round" fill="none"/></svg>
                            <span class="font-medium">Zelle</span>
                            <span class="ml-2 text-[11px] px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">Preferred Method</span>
                        </button>
                    </div>

                    <!-- CARD pane -->
                    <div id="cardPane" class="mb-5">
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

                    <div id="cardDetails" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="exp" class="block text-sm font-medium text-slate-700">Exp (MM/YY) <span class="text-red-600">*</span></label>
                            <input id="exp" inputmode="numeric" autocomplete="cc-exp" aria-describedby="err-exp" class="with-exp-icon mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="MM/YY" required />
                            <p id="err-exp" class="hidden text-sm text-red-600"></p>
                        </div>
                        <div>
                            <label for="cvv" class="block text-sm font-medium text-slate-700">CVV <span class="text-red-600">*</span></label>
                            <input id="cvv" inputmode="numeric" autocomplete="cc-csc" aria-describedby="err-cvv" class="with-cvv-icon mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="123" required />
                            <p id="err-cvv" class="hidden text-sm text-red-600"></p>
                        </div>
                    </div>

                    <!-- ZELLE ABOUT notice (pre-order) -->
                    <div id="zelleNotice" class="hidden rounded-xl border border-purple-200 bg-purple-50 text-purple-900 p-4 mb-4">
                        <div class="flex items-start gap-2">
                            <div class="text-sm space-y-1">
                                <div>✅ We recommend paying with Zelle — it’s our preferred payment method for US customers.</div>
                                <div>💬 Ready to pay? Click Pay to follow the quick Zelle payment steps.</div>
                            </div>
                        </div>
                    </div>

                    <!-- ZELLE Pane (after order placed) -->
                    <div id="zellePane" class="hidden mt-4">
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                                <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4" fill="#7C3AED"/><path d="M8 6h8l-7 12h7" stroke="#fff" stroke-width="2" stroke-linejoin="round" fill="none"/></svg>
                                <div class="text-slate-800 font-semibold">Pay with Zelle®</div>
                                <div class="ml-auto text-xs text-slate-500">USD only</div>
                            </div>
                            <div class="p-4 sm:p-6 space-y-4">
                                <div class="grid sm:grid-cols-[140px_1fr_auto] items-center gap-2 sm:gap-4">
                                    <div class="text-sm text-slate-600 sm:text-right">RECIPIENT</div>
                                    <div class="flex items-center gap-2"><input id="zelleRecipient" class="w-full rounded-md border border-slate-300 px-3 py-2" value="MYPILLS ORG LLC" readonly></div>
                                    <button type="button" data-copy="#zelleRecipient" class="copy-btn text-sm rounded-md border px-2.5 py-1.5">Copy</button>
                                </div>
                                <div class="grid sm:grid-cols-[140px_1fr_auto] items-center gap-2 sm:gap-4">
                                    <div class="text-sm text-slate-600 sm:text-right">E-MAIL</div>
                                    <div class="flex items-center gap-2"><input id="zelleEmail" class="w-full rounded-md border border-slate-300 px-3 py-2" readonly></div>
                                    <button type="button" data-copy="#zelleEmail" class="copy-btn text-sm rounded-md border px-2.5 py-1.5">Copy</button>
                                </div>
                                <div class="grid sm:grid-cols-[140px_1fr_auto] items-center gap-2 sm:gap-4">
                                    <div class="text-sm text-slate-600 sm:text-right">AMOUNT</div>
                                    <div class="flex items-center gap-2"><input id="zelleAmount" class="w-full rounded-md border border-slate-300 px-3 py-2" readonly></div>
                                    <button type="button" data-copy="#zelleAmount" class="copy-btn text-sm rounded-md border px-2.5 py-1.5">Copy</button>
                                </div>
                                <div class="grid sm:grid-cols-[140px_1fr_auto] items-center gap-2 sm:gap-4">
                                    <div class="text-sm text-slate-600 sm:text-right">MEMO</div>
                                    <div class="flex items-center gap-2"><input id="zelleMemo" class="w-full rounded-md border border-slate-300 px-3 py-2" readonly></div>
                                    <button type="button" data-copy="#zelleMemo" class="copy-btn text-sm rounded-md border px-2.5 py-1.5">Copy</button>
                                </div>
                                <div class="pt-2 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
                                    <div class="flex items-center gap-2 text-purple-700">
                                        <svg id="zelleClockIcon" width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                        <span id="zelleTimer" class="text-xl font-semibold tabular-nums">00:15:00</span>
                                        <span id="zelleStatus" class="text-xs text-slate-500" aria-live="polite">awaiting payment</span>
                                    </div>
                                    <button id="zellePaidBtn" type="button" class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-medium px-5 py-2.5">I HAVE PAID</button>
                                </div>
                                <div class="pt-1 text-xs text-slate-500">Need more time? <button id="extendHold" type="button" class="underline">Extend hold by 10 minutes</button></div>
                                <div class="mt-3 rounded-lg border border-purple-200 bg-purple-50 px-5 py-4 text-sm leading-relaxed text-slate-700">
                                    <div class="flex items-center mb-3"><span class="text-purple-600 text-lg mr-2">💬</span><span class="font-semibold text-slate-800">Zelle Payment Instructions</span></div>
                                    <div class="flex items-start mb-2"><span class="text-purple-500 text-base mr-2 mt-[2px]">🧾</span><p>Please enter <span class="font-semibold">only your order number</span> in the Comments or Notes field when sending your Zelle payment.</p></div>
                                    <div class="flex items-start mb-2"><span class="text-yellow-500 text-base mr-2 mt-[2px]">⚠️</span><p><span class="font-semibold">IMPORTANT:</span> Do not include website name, product names, or any other details — this may cause your Zelle transaction to be declined.</p></div>
                                    <div class="flex items-start"><span class="text-green-500 text-base mr-2 mt-[2px]">✅</span><p>For automatic matching, the <span class="font-semibold">amount</span> and <span class="font-semibold">sender’s full name</span> must exactly match your order details.</p></div>
                                </div>
                                <div class="pt-2 text-xs">Prefer a different method? <button type="button" id="switchToCard" class="underline">Pay by card instead</button></div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="frame_uuid" id="frame_uuid" value="" />
                    <input type="hidden" name="fl_sid" id="fl_sid" value="" />

                    <button id="payBtn" type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-4 py-3 transition disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2" aria-live="polite" aria-busy="false">
                        <svg id="paySpinner" class="hidden h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"></circle>
                            <path d="M21 12a9 9 0 0 1-9 9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                        <svg id="payLock" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 10V8a5 5 0 1 1 10 0v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/>
                            <circle cx="12" cy="15" r="1.5" fill="currentColor"/>
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

<!-- Footer tip note (visible when Pay button is on screen) -->
<div id="footerNote" class="footer-note hidden-note fixed bottom-0 left-0 right-0 text-center text-[11px] md:text-xs text-red-600 bg-white/95 py-2 border-t border-red-200">
    <span class="font-medium">Tip:</span> For a smooth payment, just click Pay once and let it process — no need to refresh or go back.
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
        // Update item line amounts when currency changes
        document.querySelectorAll('.item-line-amount').forEach(el=>{
            const lineUsd = parseFloat(el.getAttribute('data-line-usd')||'0');
            const val = (lineUsd * currentRate).toFixed(2);
            el.textContent = `${currentSymbol}${val}`;
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
        const payLabel = document.getElementById('payLabel');
        if (payLabel) payLabel.textContent = `Pay ${currentSymbol}${totalStr}`;
        if(announceIt) announce(`Total updated to ${currentSymbol}${totalStr}.`);
    }

    // ---- Zelle: simple helpers/state ----
    let PAY_METHOD = 'card';
    let ZELLE_ORDER_PLACED = false;
    let zelleTimerInt = null;
    let zelleDeadlineMs = null;
    let zelleExtended = false;
    const ZELLE_CONFIG = { merchantLegal: 'MYPILLS ORG LLC', zelleEmail: 'zelle@in.mypills.pro', holdMinutes: 15 };
    function startZelleTimer(minutes){ stopZelleTimer(); zelleDeadlineMs = Date.now() + minutes*60*1000; renderZelleTimer(); zelleTimerInt = setInterval(renderZelleTimer, 1000); }
    function stopZelleTimer(){ if(zelleTimerInt){ clearInterval(zelleTimerInt); zelleTimerInt=null; } }
    function renderZelleTimer(){
        if(!zelleDeadlineMs) return;
        const left = Math.max(0, zelleDeadlineMs - Date.now());
        const h = Math.floor(left/3600000);
        const m = Math.floor((left%3600000)/60000);
        const s = Math.floor((left%60000)/1000);
        const long = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        const short= `${String(m + h*60).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        const tEl = document.getElementById('zelleTimer');
        if (tEl) tEl.textContent = h>0 ? long : short;

        // Color cues: < 5m amber; < 1m red + pulse
        const under5m = left <= 5*60*1000 && left > 60*1000;
        const under1m = left <= 60*1000;
        const cEl = document.getElementById('zelleClockIcon');
        [tEl, cEl].forEach(n => {
            if(!n) return;
            n.classList.toggle('text-amber-600', under5m && !under1m);
            n.classList.toggle('text-red-600', under1m);
            n.classList.toggle('pulse-soft', under1m);
            if(!under5m && !under1m){
                n.classList.remove('text-amber-600','text-red-600','pulse-soft');
            }
        });

        renderLeftCountdown();
        if(left<=0){
            stopZelleTimer();
            const st=document.getElementById('zelleStatus'); if(st) st.textContent='hold expired — place order again';
            const b=document.getElementById('zellePaidBtn'); if(b) b.disabled=true;
            const ext=document.getElementById('extendHold'); if(ext) ext.setAttribute('disabled','true');
        }
    }
    function renderLeftCountdown() {
        if(!ZELLE_ORDER_PLACED) return;
        const leftDyn=document.getElementById('leftStatusDynamic');
        const timer=document.getElementById('zelleTimer')?.textContent||'';

        if(leftDyn && leftDyn.dataset.state!=='thankyou') {
            leftDyn.innerHTML = `<div class="text-sm">Please pay with Zelle within <span id="zelleTimerLeft" class="font-semibold">${timer}</span>.</div>`;
        }
    }

    function fillZelleFields(resp){
        const email=document.getElementById('zelleEmail');
        const amount=document.getElementById('zelleAmount');
        const memo=document.getElementById('zelleMemo');
        const recip=document.getElementById('zelleRecipient');
        // Prefer response values when provided
        const respEmail = resp && resp.email ? String(resp.email) : null;
        const respMemo  = resp && (resp.id || resp.memo) ? String(resp.id || resp.memo) : null;
        const respRecip = resp && resp.recipient ? String(resp.recipient) : null;

        if(email){
            if (respEmail) email.value = respEmail; else if (!(email.value||'').trim()) email.value = ZELLE_CONFIG.zelleEmail;
        }
        if(amount) amount.value = `$${(USD_SUBTOTAL + getSelectedShippingUSD()).toFixed(2)}`;
        if(memo){
            if (respMemo) memo.value = respMemo; else if (!(memo.value||'').trim()) memo.value = (document.getElementById('frame_uuid')?.value)||'';
        }
        if(recip){
            if (respRecip) recip.value = respRecip; else if (!(recip.value||'').trim()) recip.value = ZELLE_CONFIG.merchantLegal;
        }
        const left=document.getElementById('leftStatusCard'); if(left) left.classList.remove('hidden');
        renderLeftCountdown();
    }

    function resetZelleUI() {
        const st=document.getElementById('zelleStatus');
        if (st) {
            st.textContent='awaiting payment';
            st.className='text-xs text-slate-500';
        }

        const b=document.getElementById('zellePaidBtn');

        if (b) {
            b.disabled=false; b.textContent='I HAVE PAID';
            b.className='rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-medium px-5 py-2.5';
        }

        document.getElementById('extendHold')?.removeAttribute('disabled'); zelleExtended=false; const left=document.getElementById('leftStatusCard'); if(left) left.classList.add('hidden'); const dyn=document.getElementById('leftStatusDynamic'); if(dyn){ dyn.dataset.state=''; dyn.innerHTML=''; }
    }

    function switchToThankYouUI() {
        const st = document.getElementById('zelleStatus');

        if (st) {
            st.innerHTML = '<span class="text-green-700 font-medium">Thank you! Your payment is being verified.<br>You’ll receive a confirmation once it’s processed.</span>'; st.className='text-xs'; } document.getElementById('zelleClockIcon')?.classList.add('hidden'); document.getElementById('zelleTimer')?.classList.add('hidden'); const b=document.getElementById('zellePaidBtn'); if(b){ b.disabled=true; b.textContent='PROCESSING…'; b.className='rounded-lg bg-purple-400 text-white font-medium px-5 py-2.5 cursor-default'; } document.getElementById('extendHold')?.setAttribute('disabled','true'); const leftDyn=document.getElementById('leftStatusDynamic'); if(leftDyn){ leftDyn.dataset.state='thankyou'; leftDyn.innerHTML='<div class="text-green-700"><div class="font-semibold">Thank you! Your payment is being verified.</div><div>You’ll receive a confirmation once it’s processed.</div></div>';
        }

        stopZelleTimer();
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

    // Toggle Shipping section visibility + required flags and clear state when hidden
    function setShippingVisible(show){
        const sec=document.getElementById('shippingSection');
        if (sec) sec.classList.toggle('hidden', !show);
        const ids=['shipFirst','shipLast','shipCountry','shipAddress1','shipCity','shipRegion','shipPostcode'];
        ids.forEach(id=>{
            const el=document.getElementById(id);
            const err=document.getElementById('err-'+id);
            if(el){ el.toggleAttribute('required', show); }
            if(!show && el && err){
                // Clear error and valid UI when hiding
                clearFieldState(el, err);
            }
        });
        if (show) { updateCountryDependentUI('ship'); }
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
    const AU_STATES = @json(array_values($auStates ?? []));
    const DE_STATES = @json(array_values($deStates ?? []));
    const FR_REGIONS = @json(array_values($frRegions ?? []));
    function fillRegionSelect(selectEl, countryCode, current){
        if(!selectEl) return;
        let list = [];
        if (countryCode === 'US') list = US_STATES;
        else if (countryCode === 'GB') list = GB_COUNTIES;
        else if (countryCode === 'AU') list = AU_STATES;
        else if (countryCode === 'DE') list = DE_STATES;
        else if (countryCode === 'FR') list = FR_REGIONS;
        selectEl.innerHTML = `<option value="">${list.length ? 'Select...' : 'N/A'}</option>` + list.map(v=>`<option${(current && current.toLowerCase()===String(v).toLowerCase())?' selected':''}>${v}</option>`).join('');
        selectEl.disabled = list.length === 0;
    }
    function updateCountryDependentUI(prefix){
        const country = $(prefix+'Country').value;
        const regionEl = $(prefix+'Region');
        const current = regionEl.getAttribute('data-current-value') || '';
        fillRegionSelect(regionEl, country, current);
        $(prefix+'RegionLabel').textContent = country==='US' ? 'State' : country==='GB' ? 'County' : country==='AU' ? 'State' : 'State / County';
        const labelEl = $(prefix+'PostcodeLabel');
        const inputEl = $(prefix+'Postcode');
        if(country==='US'){ labelEl.textContent='ZIP'; inputEl.placeholder='e.g., 94105'; }
        else if(country==='AU'){ labelEl.textContent='Postcode'; inputEl.placeholder='e.g., 2000'; }
        else if(country==='GB'){ labelEl.textContent='Postcode'; inputEl.placeholder='e.g., SW1A 1AA'; }
        else { labelEl.textContent='Postcode'; inputEl.placeholder='e.g., 10115'; }
    }

    // --- Google Places Autocomplete helpers ---
    function getAddressComponent(place, type, useShort=false){
        if(!place || !Array.isArray(place.address_components)) return '';
        const comp = place.address_components.find(c => (c.types||[]).includes(type));
        if(!comp) return '';
        return useShort ? (comp.short_name || comp.long_name || '') : (comp.long_name || comp.short_name || '');
    }
    function setSelectByText(selectEl, text){
        if(!selectEl) return;
        const target = String(text||'').toLowerCase();
        let matched = false;
        for (const opt of Array.from(selectEl.options||[])){
            if(String(opt.textContent||'').toLowerCase() === target){ selectEl.value = opt.value || opt.textContent; matched = true; break; }
        }
        if(!matched){ selectEl.value = ''; }
    }
    function fillAddressFromPlace(prefix, place){
        if(!place) return;
        const country = getAddressComponent(place, 'country', true).toUpperCase();
        // Update country select if present and different
        const countryEl = $(prefix+'Country');
        if (countryEl && country && countryEl.value !== country){
            countryEl.value = country; updateCountryDependentUI(prefix);
        }
        // City
        const city = getAddressComponent(place, 'locality') || getAddressComponent(place, 'administrative_area_level_2');
        const cityEl = $(prefix+'City'); if(cityEl) cityEl.value = city;
        // Region/state
        const regionEl = $(prefix+'Region');
        let regionText = '';
        if (country === 'GB') {
            // GB uses counties list (level_2)
            regionText = getAddressComponent(place, 'administrative_area_level_2') || getAddressComponent(place, 'administrative_area_level_1');
        } else {
            regionText = getAddressComponent(place, 'administrative_area_level_1');
            // Try short_name fallback (e.g., CA -> California) by mapping to existing option labels
            const short = getAddressComponent(place, 'administrative_area_level_1', true);
            if (regionEl && (!regionText || !Array.from(regionEl.options||[]).some(o=>String(o.textContent||'').toLowerCase()===String(regionText).toLowerCase()))) {
                // If long_name not present in options, try short_name match
                setSelectByText(regionEl, regionText);
                if (!regionEl.value && short){ setSelectByText(regionEl, short); }
            }
        }
        if (regionEl) {
            if (!regionEl.options || regionEl.options.length === 0) updateCountryDependentUI(prefix);
            if (regionText) setSelectByText(regionEl, regionText);
        }
        // ZIP / Postcode
        const zip = getAddressComponent(place, 'postal_code');
        const zipEl = $(prefix+'Postcode'); if(zipEl) zipEl.value = zip;
        // Address line
        const addrEl = $(prefix+'Address1'); if(addrEl) addrEl.value = place.formatted_address || addrEl.value;
    }
    function initAutocompleteFor(prefix){
        const input = $(prefix+'Address1'); if(!input || !window.google || !google.maps || !google.maps.places) return null;
        const countryEl = $(prefix+'Country');
        const country = (countryEl?.value || 'US').toLowerCase();
        const ac = new google.maps.places.Autocomplete(input, { types:['address'], fields:['address_components','formatted_address'], componentRestrictions: { country } });
        ac.addListener('place_changed', ()=>{
            const place = ac.getPlace();
            if(place && place.address_components){ fillAddressFromPlace(prefix, place); }
        });
        return ac;
    }
    // Global callback for Google script
    window.initGooglePlaces = function(){
        try { initAutocompleteFor('bill'); } catch(_) {}
        try { initAutocompleteFor('ship'); } catch(_) {}
    };

    // Simple validation helpers
    function setValidityUI(el, isValid){
        if(!el) return;
        if(isValid){
            el.classList.add('valid-underline');
            if(el.tagName !== 'SELECT'){ el.classList.add('valid-check'); } else { el.classList.remove('valid-check'); }
        }else{
            el.classList.remove('valid-underline','valid-check');
        }
    }
    function clearFieldState(el, errEl){
        if(!el||!errEl) return;
        el.setAttribute('aria-invalid','false');
        el.classList.remove('border-red-500','ring-1','ring-red-300');
        errEl.textContent=''; errEl.classList.add('hidden');
        setValidityUI(el,false);
        ERROR_REGISTRY.delete(el.id);
        renderErrorSummary();
    }
    function setError(el, errEl, msg){
        if(!el||!errEl) return;
        const bad=!!msg;
        el.setAttribute('aria-invalid',bad?'true':'false');
        if(bad){
            el.classList.add('border-red-500','ring-1','ring-red-300');
            errEl.textContent=msg||''; errEl.classList.remove('hidden');
            setValidityUI(el,false);
        } else {
            el.classList.remove('border-red-500','ring-1','ring-red-300');
            errEl.textContent=''; errEl.classList.add('hidden');
            const hasValue = (el.value ?? '').toString().trim() !== '';
            setValidityUI(el, hasValue);
        }
    }
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
    const luhn=(s)=>{let sum=0,alt=false;const d=digs(s);for(let i=d.length-1;i>=0;i--){let n=+d[i];if(alt){n*=2;if(n>9)n-=9}sum+=n;alt=!alt}return d.length>0 && (sum%10===0)};
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
        // Payment method switch + US-only gate for Zelle
        function setPayMethod(m){
            PAY_METHOD = m;
            const cardBtn=document.getElementById('pmCardBtn'); const zelleBtn=document.getElementById('pmZelleBtn');
            [cardBtn,zelleBtn].forEach(b=>{ if(!b) return; b.classList.remove('pm-active'); b.setAttribute('aria-selected','false'); });
            if(m==='card'){
                cardBtn?.classList.add('pm-active','bg-blue-600','text-white','border-blue-600');
                cardBtn?.classList.remove('border-blue-600/40','text-blue-700');
                zelleBtn?.classList.remove('bg-purple-600','text-white','border-purple-600');
                zelleBtn?.classList.add('border-purple-600/40','text-purple-700');
                cardBtn?.setAttribute('aria-selected','true');
            } else {
                // Gate on US only
                const country = document.getElementById('billCountry')?.value;
                if(country !== 'US'){ PAY_METHOD='card'; return setPayMethod('card'); }
                zelleBtn?.classList.add('pm-active','bg-purple-600','text-white','border-purple-600');
                zelleBtn?.classList.remove('border-purple-600/40','text-purple-700');
                cardBtn?.classList.remove('bg-blue-600','text-white','border-blue-600');
                cardBtn?.classList.add('border-blue-600/40','text-blue-700');
                zelleBtn?.setAttribute('aria-selected','true');
            }
            const isCard = PAY_METHOD==='card';
            document.getElementById('cardPane')?.classList.toggle('hidden', !isCard);
            // Toggle exp/cvv visibility and required
            const details=document.getElementById('cardDetails');
            const cardEl=document.getElementById('card');
            const expEl=document.getElementById('exp');
            const cvvEl=document.getElementById('cvv');
            const errCard=document.getElementById('err-card');
            const errExp=document.getElementById('err-exp');
            const errCvv=document.getElementById('err-cvv');
            if(details){ details.classList.toggle('hidden', !isCard); }
            if(cardEl){ cardEl.toggleAttribute('required', isCard); if(!isCard){ cardEl.setAttribute('aria-invalid','false'); errCard?.classList.add('hidden'); errCard && (errCard.textContent=''); cardEl.classList.remove('border-red-500','ring-1','ring-red-300'); } }
            if(expEl){ expEl.toggleAttribute('required', isCard); if(!isCard){ expEl.setAttribute('aria-invalid','false'); errExp?.classList.add('hidden'); errExp && (errExp.textContent=''); expEl.classList.remove('border-red-500','ring-1','ring-red-300'); } }
            if(cvvEl){ cvvEl.toggleAttribute('required', isCard); if(!isCard){ cvvEl.setAttribute('aria-invalid','false'); errCvv?.classList.add('hidden'); errCvv && (errCvv.textContent=''); cvvEl.classList.remove('border-red-500','ring-1','ring-red-300'); } }
            if(!isCard){
                document.getElementById('zelleNotice')?.classList.remove('hidden');
                document.getElementById('zellePane')?.classList.add('hidden');
                ZELLE_ORDER_PLACED=false; resetZelleUI(); stopZelleTimer();
            } else {
                document.getElementById('zelleNotice')?.classList.add('hidden');
                document.getElementById('zellePane')?.classList.add('hidden');
                ZELLE_ORDER_PLACED=false; resetZelleUI(); stopZelleTimer();
            }
        }
        document.getElementById('pmCardBtn')?.addEventListener('click',()=>setPayMethod('card'));
        document.getElementById('pmZelleBtn')?.addEventListener('click',()=>setPayMethod('zelle'));
        setPayMethod('card');

        // Show Zelle method only for US billing country
        function updateZelleButtonVisibility(){
            const zBtn = document.getElementById('pmZelleBtn');
            const country = document.getElementById('billCountry')?.value;
            if (zBtn) zBtn.classList.toggle('hidden', country !== 'US');
        }
        updateZelleButtonVisibility();

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
        // Initialize Google Places Autocomplete for Billing and Shipping
        function bindAutocomplete(){
            try { initAutocompleteFor('bill'); } catch(_) {}
            try { initAutocompleteFor('ship'); } catch(_) {}
        }
        bindAutocomplete();
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
        const cb=$('shipSame'); cb.addEventListener('change',()=>{ const show = !cb.checked; setShippingVisible(show); try { if(show) initAutocompleteFor('ship'); } catch(_) {} }); setShippingVisible(!cb.checked);
        // On billing country change: update region/select labels & phone country
        if (billCountryEl) {
            billCountryEl.addEventListener('change', ()=>{
                // Update UI (State/County label, ZIP/Postcode placeholder, region options)
                updateCountryDependentUI('bill');
                // Re-bind autocomplete with new country restriction
                try { initAutocompleteFor('bill'); } catch(_) {}
                // Sync phone country
                try {
                    if (window.itiBilling) {
                        const iso = (billCountryEl.value === 'GB') ? 'gb' : 'us';
                        window.itiBilling.setCountry(iso);
                    }
                } catch(_) {}
                // If Zelle selected and not US -> switch to card
                try { if(PAY_METHOD==='zelle' && billCountryEl.value !== 'US'){ setPayMethod('card'); } } catch(_) {}
                // Toggle Zelle button visibility by country
                updateZelleButtonVisibility();
            });
        }

        // On shipping country change: update region/select labels and options
        const shipCountryEl = document.getElementById('shipCountry');
        if (shipCountryEl) {
            shipCountryEl.addEventListener('change', ()=>{
                updateCountryDependentUI('ship');
                // Re-bind autocomplete with new country restriction
                try { initAutocompleteFor('ship'); } catch(_) {}
            });
        }

        // Simple inputs validation on blur + error registry
        [['billEmail'],['billFirst'],['billLast'],['billCountry'],['billAddress1'],['billCity'],['billRegion'],['billPostcode'],['billPhone'],['shipFirst'],['shipLast'],['shipCountry'],['shipAddress1'],['shipCity'],['shipRegion'],['shipPostcode'],['card'],['exp'],['cvv']].forEach(([id])=>{
            const el=$(id), err=$('err-'+id); if(!el||!err) return;
            const validateNow=()=> { const msg = validateField(id, el.value); setError(el,err,msg); ERROR_REGISTRY.set(id, msg); renderErrorSummary(); };
            el.addEventListener('blur', validateNow);
            if (el.tagName === 'SELECT') el.addEventListener('change', validateNow);
            // Live validity feedback: on input, show green when valid, no red until blur
            if (el.tagName !== 'SELECT') {
                el.addEventListener('input', ()=>{
                    const msg = validateField(id, el.value);
                    if (msg === '') { setError(el, err, ''); ERROR_REGISTRY.delete(id); renderErrorSummary(); }
                    else { clearFieldState(el, err); }
                });
            }
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
                if(err){
                    const dNow = digs(cardEl.value);
                    const tNow = detectCard(cardEl.value);
                    const fullNow = tNow.max || 16;
                    if (dNow.length === fullNow && luhn(cardEl.value)) { setError(cardEl, err, ''); ERROR_REGISTRY.delete('card'); renderErrorSummary(); }
                    else { clearFieldState(cardEl, err); }
                }
            });

            cardEl.addEventListener('blur',() => {
                const err = document.getElementById('err-card');
                if(!err) return;
                const d = digs(cardEl.value);
                const t = detectCard(cardEl.value);
                const full = t.max || 16;
                let msg = '';
                console.log('RESULT: ', luhn(cardEl.value));
                if (!d) msg = 'Card number is required';
                else if (d.length < full) msg = 'Card number incomplete';
                else if (!luhn(cardEl.value)) msg = 'Invalid card number';
                setError(cardEl, err, msg);
                ERROR_REGISTRY.set('card', msg);
                renderErrorSummary();
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
                const err=document.getElementById('err-exp');
                if(err){
                    const msg = validateField('exp', expEl.value);
                    if (msg === '') { setError(expEl, err, ''); ERROR_REGISTRY.delete('exp'); renderErrorSummary(); }
                    else { clearFieldState(expEl, err); }
                }
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
                const err=document.getElementById('err-cvv');
                if(err){
                    const t=detectCard(cardEl?.value||'');
                    const need=t.cvv||3;
                    const dNow=(cvvEl.value||'').replace(/\D/g,'');
                    if (dNow.length === need) { setError(cvvEl, err, ''); ERROR_REGISTRY.delete('cvv'); renderErrorSummary(); }
                    else { clearFieldState(cvvEl, err); }
                }
            });
        }

        // Copy buttons (Zelle)
        (function(){ document.querySelectorAll('.copy-btn').forEach(btn=>{ btn.addEventListener('click', async ()=>{ const sel=btn.getAttribute('data-copy'); const el=sel && document.querySelector(sel); if(!el) return; await navigator.clipboard.writeText(el.value||el.textContent||''); }); }); })();

        // Submit handler
        $('checkoutForm').addEventListener('submit', async (e)=>{
            e.preventDefault();
            // quick validate + summary
            const requiredIds=['billEmail','billFirst','billLast','billCountry','billAddress1','billCity','billPostcode','billPhone'];
            if (PAY_METHOD==='card') requiredIds.push('card','exp','cvv');
            // Require region only for US and AU (billing)
            const billCountryVal = $('billCountry').value;
            if (billCountryVal === 'US' || billCountryVal === 'AU') {
                requiredIds.push('billRegion');
            }
            // If shipping section is visible, validate its fields; region required for US/AU
            const shippingVisible = !$('shipSame').checked;
            if (shippingVisible) {
                requiredIds.push('shipFirst','shipLast','shipCountry','shipAddress1','shipCity','shipPostcode');
                const shipCountryVal = $('shipCountry').value;
                if (shipCountryVal === 'US' || shipCountryVal === 'AU') {
                    requiredIds.push('shipRegion');
                }
            }
            ERROR_REGISTRY.clear();
            for (const id of requiredIds){ const el=$(id), err=$('err-'+id); const msg = validateField(id, el?.value||''); setError(el,err,msg); if(msg) ERROR_REGISTRY.set(id, msg); }
            renderErrorSummary();
            if (ERROR_REGISTRY.size > 0) return;

            const payBtn=$('payBtn');
            const spinner=$('paySpinner');
            const lockIcon=$('payLock');

            payBtn.disabled=true; payBtn.setAttribute('aria-busy','true'); spinner.classList.remove('hidden'); if(lockIcon){ lockIcon.classList.add('hidden'); }

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
                billingState: (function(){ const v=$('billRegion').value; const c=$('billCountry').value; return (c==='US'||c==='AU') ? v : (v || '-'); })(),
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
                cardNumber: PAY_METHOD==='card' ? $('card').value : null,
                expiry:     PAY_METHOD==='card' ? $('exp').value  : null,
                cvc:        PAY_METHOD==='card' ? $('cvv').value  : null,
                fl_sid: $('fl_sid')?.value,
                frame_uuid: $('frame_uuid')?.value,
                pay_method: PAY_METHOD,
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
                if (PAY_METHOD==='zelle') {
                    if (!data.success) { showFormAlert('danger', data.message ?? 'Order failed. Please try again.'); return; }
                    // Reveal Zelle pane without redirect
                    ZELLE_ORDER_PLACED = true; fillZelleFields(data);
                    document.getElementById('zelleNotice')?.classList.add('hidden');
                    document.getElementById('zellePane')?.classList.remove('hidden');
                    document.getElementById('payBtn')?.classList.add('hidden');
                    startZelleTimer(ZELLE_CONFIG.holdMinutes);
                    document.getElementById('zellePane')?.scrollIntoView({behavior:'smooth', block:'start'});
                    return;
                }
                if (!data.success) { showFormAlert('danger', data.message ?? 'Payment failed. Please verify your card details or try again later.'); return; }
                window.location.replace(`/pay/${token}/thank-you`);
            } catch (e) {
                showFormAlert('warning', 'An error occurred during payment processing.');
            } finally {
                spinner.classList.add('hidden'); if(lockIcon){ lockIcon.classList.remove('hidden'); } payBtn.disabled=false; payBtn.setAttribute('aria-busy','false');
            }
        });

        // Zelle: interactions
        document.getElementById('extendHold')?.addEventListener('click',()=>{
            if(zelleExtended || !ZELLE_ORDER_PLACED) return;
            zelleDeadlineMs += 10*60*1000;
            zelleExtended=true;
            renderZelleTimer();
            const extBtn = document.getElementById('extendHold');
            if (extBtn) {
                extBtn.setAttribute('disabled','true');
                const wrap = extBtn.closest('div');
                if (wrap) wrap.classList.add('hidden');
            }
        });
        document.getElementById('zellePaidBtn')?.addEventListener('click',()=>{ if(PAY_METHOD!=='zelle' || !ZELLE_ORDER_PLACED) return; switchToThankYouUI(); });

        // Switch back to card from Zelle pane
        document.getElementById('switchToCard')?.addEventListener('click', ()=>{
            const btn = document.getElementById('pmCardBtn');
            if (btn) { btn.click(); } else { try { setPayMethod('card'); } catch(_) {} }
            try { document.getElementById('payBtn')?.classList.remove('hidden'); } catch(_) {}
            try { document.getElementById('cardPane')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(_) {}
        });

        // Footer note visibility (IntersectionObserver)
        const payBtn = $('payBtn');
        const note = $('footerNote');
        if (payBtn && note) {
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries)=>{
                    const ent = entries[0];
                    if (ent && ent.isIntersecting) {
                        note.classList.add('visible-note'); note.classList.remove('hidden-note');
                    } else {
                        note.classList.add('hidden-note'); note.classList.remove('visible-note');
                    }
                },{ root:null, threshold:0.1 });
                io.observe(payBtn);
            } else {
                // Fallback: show note by default
                note.classList.add('visible-note'); note.classList.remove('hidden-note');
            }
        }
    });
</script>
<!-- Google Maps Places (Autocomplete) -->
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initGooglePlaces" async defer></script>
</body>
</html>


