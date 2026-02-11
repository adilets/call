<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon2.png') }}" />
    <title>Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.11/build/css/intlTelInput.min.css">
    <style> body{font-family:Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Noto Sans, Ubuntu, Cantarell, Helvetica Neue, Arial, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"} </style>
    <style>
        .iti{width:100%}
        .iti__tel-input{width:100%}
        /* Payment rows styling */
        .pm-row{border:1px solid rgba(15,23,42,.08);border-radius:14px;background:#fff;transition:.22s ease;cursor:pointer}
        .pm-row:hover{box-shadow:0 6px 18px -8px rgba(2,6,23,.18)}
        .pm-row[aria-checked="true"]{border-color:rgba(59,130,246,.45);box-shadow:0 0 0 4px rgba(59,130,246,.12)}
        .pm-content{max-height:0;opacity:0;overflow:hidden;transform:translateY(-6px);
            transition:max-height .28s ease,opacity .18s ease,transform .28s ease,padding .28s ease;
            padding:0 1rem 0 1rem}
        .pm-content.open{max-height:1200px;opacity:1;transform:translateY(0);padding:0 1rem 1rem 1rem}
        .brand-badge{display:inline-flex;align-items:center;gap:.35rem;font-size:12px;border-radius:999px;padding:.15rem .5rem}
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
@php
    /** @var \App\Models\Order $order */
    $items = isset($order) ? ($order->items ?? collect()) : collect();
    $usdSubtotal = $items->sum(fn ($i) => (float) ($i->qty ?? 0) * (float) ($i->unit_price ?? 0));
    $orderTotal = (float) ($order->total_price ?? $usdSubtotal);
    $shippingMethods = collect($shippingMethods ?? []);
    $selectedShippingId = isset($order) ? ($order->shipping_method_id ?? null) : null;
    $selectedShipping = $shippingMethods->firstWhere('id', $selectedShippingId) ?? $shippingMethods->first();
    $shippingCostUsd = 0.0;
    $selectedRate = (float) ($currencies[$selectedCurrency] ?? 1.0);
    $currencySymbol = $currencySymbols[$selectedCurrency] ?? '$';
    $orderRate = (float) ($order?->rate ?? 1.0);
    $usdSubtotal = $order?->currency && strtoupper($order->currency) !== 'USD'
        ? $orderTotal / max($orderRate, 0.000001)
        : $orderTotal;
@endphp
<div class="max-w-6xl mx-auto p-4 lg:p-8">
    <div class="flex justify-center">

        <!-- LEFT -->
        <aside class="space-y-4 lg:sticky lg:top-6 self-start hidden">

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
        <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-2xl">
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
                <input type="hidden" name="payeasy_fp_visitor_id" id="payeasy_fp_visitor_id" value="" />
                <input type="hidden" name="payeasy_fp_request_id" id="payeasy_fp_request_id" value="" />
                <!-- Billing -->
                <section class="mb-8">
                    <div class="flex items-center justify-between text-sm font-semibold text-slate-700 mb-2">
                        <span>Total to pay</span>
                        <span id="totalToPay">{{ $currencySymbol }}{{ number_format($usdSubtotal * $selectedRate, 2) }}</span>
                    </div>
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

                    @php
                        $countryKeys = array_keys($countries ?? []);
                        $firstAllowed = $countryKeys[0] ?? 'US';
                        $currentBillCountry = in_array(($billing?->country ?? ''), $countryKeys, true)
                            ? ($billing?->country)
                            : $firstAllowed;
                    @endphp
                    <div class="mb-4">
                        <label for="billCountry" class="block text-sm font-medium text-slate-700">Country <span class="text-red-600">*</span></label>
                        <select id="billCountry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" required>
                            @foreach(($countries ?? []) as $code => $name)
                                <option value="{{ $code }}" {{ $code === $currentBillCountry ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
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
                        <div id="billRegionField">
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

                    @php $currentShipCountry = $currentBillCountry; @endphp
                    <div class="mb-4">
                        <label for="shipCountry" class="block text-sm font-medium text-slate-700">Country <span class="text-red-600">*</span></label>
                        <select id="shipCountry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            @foreach(($countries ?? []) as $code => $name)
                                <option value="{{ $code }}" {{ $code === $currentShipCountry ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
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
                        <div id="shipRegionField">
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

                    @php
                        $allowedPayMethods = $allowedPayMethods ?? ['card','zelle','venmo','cardtousdt','airwallex'];
                        $allowCard = in_array('card', $allowedPayMethods, true);
                        $allowZelle = in_array('zelle', $allowedPayMethods, true);
                        $allowVenmo = in_array('venmo', $allowedPayMethods, true);
                        $allowCardToUsdt = in_array('cardtousdt', $allowedPayMethods, true);
                        $allowSepa = in_array('airwallex', $allowedPayMethods, true);
                        $defaultPayMethod = $allowCard ? 'card' : ($allowZelle ? 'zelle' : ($allowVenmo ? 'venmo' : ($allowCardToUsdt ? 'cardtousdt' : ($allowSepa ? 'airwallex' : 'card'))));
                    @endphp

                    <!-- Payment rows (accordion) -->
                    <div class="space-y-3" role="radiogroup" aria-label="Payment method">
                        @if($allowCard)
                        <div id="row-card" class="pm-row" role="radio" aria-checked="false" data-method="card" tabindex="0">
                            <div class="flex items-center gap-3 p-4">
                                <input type="radio" name="pm" value="card" class="h-4 w-4 text-blue-600">
                                <div class="font-medium text-slate-800 flex items-center gap-2">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="12" rx="2" stroke="#0f172a" stroke-width="1.6"/><rect x="3" y="9" width="18" height="3" fill="#0f172a"/></svg>
                                    Card (Visa / MC)
                                </div>
                            </div>
                            <div class="pm-content" id="row-card-content">
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
                            </div>
                        </div>
                        @endif
                        @if($allowCardToUsdt)
                        <div id="row-cardtousdt" class="pm-row" role="radio" aria-checked="false" data-method="cardtousdt" tabindex="-1">
                            <div class="flex items-center gap-3 p-4">
                                <input type="radio" name="pm" value="cardtousdt" class="h-4 w-4 text-emerald-600">
                                <div class="font-medium text-slate-800 flex items-center gap-3 w-full">
                                    <div class="hidden sm:flex items-center gap-1.5">
                                        <img src="{{ asset('images/payment-methods/apple.png') }}" alt="Apple Pay" class="h-5 w-auto">
                                        <img src="{{ asset('images/payment-methods/google.png') }}" alt="Google Pay" class="h-5 w-auto">
                                        <img src="{{ asset('images/payment-methods/master.png') }}" alt="Mastercard" class="h-5 w-auto">
                                        <img src="{{ asset('images/payment-methods/revolute.png') }}" alt="Revolut" class="h-5 w-auto">
                                        <img src="{{ asset('images/payment-methods/visa.png') }}" alt="Visa" class="h-5 w-auto">
                                    </div>
                                    <div class="leading-tight">
                                        <div>Secure Credit/Debit Card</div>
                                        <div class="text-[13px] font-normal text-slate-500">(Processed via Crypto On-Ramp • KYC Required)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="pm-content" id="row-cardtousdt-content">
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 p-4">
                                    <div class="text-sm space-y-1">
                                        <div>
                                            <b>🔒 Secure Credit / Debit Card (Processed via Crypto On-Ramp • KYC Required)</b><br>
                                        </div>
                                        <div>Pay securely by credit or debit card. Your payment is processed as a USDC crypto on-ramp transaction, which requires identity verification (KYC).</div>
                                        <br>
                                        <div><b>Important information before you pay:</b><br><br></div>
                                        <div>
                                            <b>① Identity verification is required</b> <br>
                                            Card payments are processed as crypto on-ramp transactions and therefore legally require KYC verification.
                                        </div>
                                        <div>
                                            <b>② VPNs and proxies must be disabled</b>
                                            Using a VPN or proxy is the most common reason for verification failure.
                                            Please turn off any VPN or proxy services before starting the payment.
                                        </div>
                                        <div>
                                            <b>③ We never see or store your KYC data</b> <br>
                                            All identity verification is handled directly by our payment providers (Stripe / Paybis).
                                            Your documents and personal data are never accessed or stored by our store.
                                        </div>
                                        <div>
                                            <b>④ Verification level is decided by the provider</b> <br>
                                            Depending on the provider’s internal risk checks, you may be asked for light or full KYC.
                                            These requirements are set by the payment provider and are not controlled by us.
                                        </div>
                                        <div>
                                            <b>⑤ Refunds are handled by our store</b> <br>
                                            Because the card payment is processed as a crypto on-ramp transaction, banks cannot reverse or charge back the payment.
                                            If you need a refund, please contact us directly — refunds are processed according to our store’s refund policy.
                                        </div>
                                        <div>
                                            <b>⑥ Transaction limits</b> <br>
                                            Maximum amount per transaction:
                                            $600 / A$600 / €600 / £600 / C$600
                                            (depending on your selected currency: USD / AUD / EUR / GBP / CAD).
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        @if($allowSepa)
                        <div id="row-airwallex" class="pm-row" role="radio" aria-checked="false" data-method="airwallex" tabindex="-1">
                            <div class="flex items-center gap-3 p-4">
                                <input type="radio" name="pm" value="airwallex" class="h-4 w-4 text-teal-600">
                                <div class="font-medium text-slate-800 flex items-center gap-2">
                                    <img src="{{ asset('images/payment-methods/bank.png') }}" alt="Local Payment" class="h-5 w-auto">
                                    <div>
                                        <p>Local Payment (EU / UK / AU / US / CA)</p>
                                        <p class="text-[13px] text-slate-500 leading-5">Pay easily via SEPA, ACH, FPS, Interac or local bank transfer.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="pm-content" id="row-airwallex-content">
                                <div id="airwallexNotice" class="hidden rounded-xl border border-teal-200 bg-teal-50 text-teal-900 p-4 mb-4">
                                    <div class="font-medium mb-1">How it works:</div>
                                    <ol class="list-decimal ml-5 text-sm space-y-1">
                                        <li>Click <b>Place order</b> to generate your payment reference.</li>
                                        <li>Make a bank transfer using the payment details shown next.</li>
                                        <li>Once the transfer is complete, click <b>I HAVE PAID</b> to speed up verification.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        @endif
                        @if($allowZelle)
                        <div id="row-zelle" class="pm-row" role="radio" aria-checked="false" data-method="zelle" tabindex="-1">
                            <div class="flex items-center gap-3 p-4">
                                <input type="radio" name="pm" value="zelle" class="h-4 w-4 text-purple-600">
                                <div class="font-medium text-slate-800 flex items-center gap-2">
                                    <img src="{{ asset('images/payment-methods/zelle.png') }}" alt="Zelle" class="h-5 w-auto">
                                    Zelle
                                </div>
                            </div>
                            <div class="pm-content" id="row-zelle-content">
                                <div id="zelleNotice" class="hidden rounded-xl border border-purple-200 bg-purple-50 text-purple-900 p-4 mb-4">
                                    <div class="flex items-start gap-2">
                                        <div class="text-sm space-y-1">
                                            <div>✅ <b>We recommend paying with Zelle</b> — it's our preferred payment method for US customers.</div>
                                            <div>💬 Ready to pay? <b>Click Pay</b> to follow the quick Zelle payment steps.</div>
                                        </div>
                                    </div>
                                </div>
                                <div id="zellePane" class="hidden mt-4">
                                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                                        <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                                            <img src="{{ asset('images/payment-methods/zelle.png') }}" alt="Zelle" class="h-7 w-auto" aria-hidden="true">
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
                                                <div class="flex items-start"><span class="text-green-500 text-base mr-2 mt-[2px]">✅</span><p>For automatic matching, the <span class="font-semibold">amount</span> and <span class="font-semibold">sender's full name</span> must exactly match your order details.</p></div>
                                            </div>
                                            <div class="pt-2 text-xs">Prefer a different method? <button type="button" id="switchToCard" class="underline">Pay by card instead</button></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        @if($allowVenmo)
                        <div id="row-venmo" class="pm-row" role="radio" aria-checked="false" data-method="venmo" tabindex="-1">
                            <div class="flex items-center gap-3 p-4">
                                <input type="radio" name="pm" value="venmo" class="h-4 w-4 text-blue-600">
                                <div class="font-medium text-slate-800 flex items-center gap-2">
                                    <img src="{{ asset('images/payment-methods/venmo.png') }}" alt="Venmo" class="h-5 w-auto">
                                    Venmo <span class="brand-badge ml-2" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">Preferred</span>
                                </div>
                            </div>
                            <div class="pm-content" id="row-venmo-content">
                                <div class="rounded-xl border border-blue-200 bg-blue-50 text-blue-900 p-4">
                                    <div class="flex items-start gap-2">
                                        <div class="text-sm space-y-1">
                                            <div>✅ <b>We recommend paying with Venmo</b> for fast confirmation.</div>
                                            <div>📱 Click <b>Pay</b> to get your Venmo QR code and details.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <button id="payBtn" type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg mt-3 px-4 py-3 transition disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2" aria-live="polite" aria-busy="false">
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
    window.PayeasyFingerprint = window.PayeasyFingerprint || {};
    window.PayeasyFingerprint.publicKey = @json($fingerprintPublicKey ?? '');
</script>
<script type="module" src="{{ asset('js/payeasy-fingerprint-pro.js') }}"></script>
<script>
    // Data from server
    const SELECTED_RATE = {{ number_format($selectedRate, 6, '.', '') }};
    let currentCurr = @json($selectedCurrency);
    let currentRate = SELECTED_RATE;
    let currentSymbol = @json($currencySymbol);
    const CURRENCY_SYMBOLS = @json($currencySymbols ?? []);
    const USD_SUBTOTAL = {{ number_format($usdSubtotal, 2, '.', '') }};
    const ORDER_TOTAL_ORIGINAL = {{ number_format((float) ($order->total_price ?? $usdSubtotal), 2, '.', '') }};
    const ORDER_CURRENCY = @json(strtoupper((string) ($order->currency ?? $selectedCurrency)));
    const ORDER_RATE = {{ number_format((float) ($order->rate ?? $selectedRate ?? 1), 6, '.', '') }};

    let PAY_METHOD = null;
    function getSymbolByCurrency(code){
        const c = String(code || '').toUpperCase();
        return CURRENCY_SYMBOLS[c] || currentSymbol || c;
    }
    function updateTotalsForPayMethod(announceIt=true){
        const isUsdForced = PAY_METHOD === 'zelle' || PAY_METHOD === 'venmo';
        const payCurr = isUsdForced ? 'USD' : ORDER_CURRENCY;
        const paySymbol = getSymbolByCurrency(payCurr);
        const totalValue = isUsdForced
            ? (ORDER_CURRENCY === 'USD' ? ORDER_TOTAL_ORIGINAL : (ORDER_RATE > 0 ? ORDER_TOTAL_ORIGINAL / ORDER_RATE : ORDER_TOTAL_ORIGINAL))
            : ORDER_TOTAL_ORIGINAL;
        const totalStr = Number(totalValue || 0).toFixed(2);
        const subStr = totalStr;
        const shipStr = 'Free';

        const subtotalEl = document.getElementById('subtotal');
        const shippingEl = document.getElementById('shippingPrice');
        const totalEl = document.getElementById('total');
        const totalToPayEl = document.getElementById('totalToPay');
        if (subtotalEl) subtotalEl.textContent = paySymbol + subStr;
        if (shippingEl) shippingEl.textContent = shipStr;
        if (totalEl) totalEl.textContent = paySymbol + totalStr;
        if (totalToPayEl) totalToPayEl.textContent = paySymbol + totalStr;

        if (announceIt) announce(`Total updated to ${paySymbol}${totalStr}.`);
        return { paySymbol, totalStr, payCurr };
    }
    function updatePayLabel(){
        const { paySymbol, totalStr } = updateTotalsForPayMethod(false);
        const label = `Pay ${paySymbol}${totalStr}`.trim();
        const el = document.getElementById('payLabel');
        if (el) {
            el.textContent = label;
        }
    }

    // Helpers
    const $ = (id)=>document.getElementById(id);
    function announce(msg){ const r=$('liveRegion'); if(!r) return; r.textContent=''; setTimeout(()=>r.textContent=msg,10); }
    updatePayLabel();
    // Visible toast helper for quick user feedback
    let __toastTimer = null;
    function showToast(message){
        let t = document.getElementById('toast');
        if(!t){
            t = document.createElement('div');
            t.id = 'toast';
            t.setAttribute('role','status');
            t.style.position = 'fixed';
            t.style.left = '50%';
            t.style.bottom = '20px';
            t.style.transform = 'translateX(-50%)';
            t.style.background = '#111827';
            t.style.color = '#fff';
            t.style.padding = '8px 12px';
            t.style.borderRadius = '10px';
            t.style.fontSize = '12px';
            t.style.boxShadow = '0 6px 18px rgba(0,0,0,.25)';
            t.style.opacity = '0';
            t.style.transition = 'opacity .18s ease';
            t.style.zIndex = '9999';
            document.body.appendChild(t);
        }
        t.textContent = String(message||'');
        t.style.opacity = '1';
        if(__toastTimer) clearTimeout(__toastTimer);
        __toastTimer = setTimeout(()=>{ t.style.opacity = '0'; }, 1500);
    }

    function fillAirwallexFields(resp){
        const benef=document.getElementById('airwallexBenef');
        const iban=document.getElementById('airwallexIban');
        const bic=document.getElementById('airwallexBic');
        const amount=document.getElementById('airwallexAmount');
        const ref=document.getElementById('airwallexRef');
        const respBenef = resp && (resp.beneficiary || resp.recipient) ? String(resp.beneficiary || resp.recipient) : null;
        const respIban  = resp && resp.iban ? String(resp.iban) : null;
        const respBic   = resp && (resp.bic || resp.swift) ? String(resp.bic || resp.swift) : null;
        const respRef   = resp && (resp.id || resp.reference || resp.memo) ? String(resp.id || resp.reference || resp.memo) : null;
        if (benef) benef.value = respBenef || AIRWALLEX_CONFIG.beneficiary;
        if (iban)  iban.value  = respIban  || AIRWALLEX_CONFIG.iban;
        if (bic)   bic.value   = respBic   || AIRWALLEX_CONFIG.bic;
        if (amount){
            // amount in EUR
            const totalText = (document.getElementById('total')?.textContent || '').trim();
            if (totalText.startsWith('€')) amount.value = totalText;
            else amount.value = `€${((USD_SUBTOTAL) * (SELECTED_RATE || 1)).toFixed(2)}`;
        }
        if (ref)   ref.value   = respRef || '';
    }

    // ---- Zelle: simple helpers/state ----
    let ZELLE_ORDER_PLACED = false;
    let AIRWALLEX_ORDER_PLACED = false;
    let zelleTimerInt = null;
    let zelleDeadlineMs = null;
    let zelleExtended = false;
    const ZELLE_CONFIG = { merchantLegal: 'MYPILLS ORG LLC', zelleEmail: 'zelle@in.mypills.pro', holdMinutes: 15 };
    const AIRWALLEX_CONFIG = { beneficiary: 'MYPILLS ORG LLC', iban: 'DE89 3704 0044 0532 0130 00', bic: 'COBADEFFXXX' };
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
        if(amount) amount.value = `$${(USD_SUBTOTAL).toFixed(2)}`;
        if(memo) memo.value = respMemo || '';
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
            st.innerHTML = `<span class="text-green-700 font-medium">Thank you! Your payment is being verified.<br>You'll receive a confirmation once it's processed.</span>`; st.className='text-xs'; } document.getElementById('zelleClockIcon')?.classList.add('hidden'); document.getElementById('zelleTimer')?.classList.add('hidden'); const b=document.getElementById('zellePaidBtn'); if(b){ b.disabled=true; b.textContent='PROCESSING…'; b.className='rounded-lg bg-purple-400 text-white font-medium px-5 py-2.5 cursor-default'; } document.getElementById('extendHold')?.setAttribute('disabled','true'); const leftDyn=document.getElementById('leftStatusDynamic'); if(leftDyn){ leftDyn.dataset.state='thankyou'; leftDyn.innerHTML=`<div class="text-green-700"><div class="font-semibold">Thank you! Your payment is being verified.</div><div>You'll receive a confirmation once it's processed.</div></div>`;
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
        if (paymentErrorSticky) return;
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
    const CA_PROVINCES = @json(array_values($caProvinces ?? []));
    const ES_PROVINCES = @json(array_values($esProvinces ?? []));
    const IT_PROVINCES = @json(array_values($itProvinces ?? []));
    const DE_STATES = @json(array_values($deStates ?? []));
    const FR_REGIONS = @json(array_values($frRegions ?? []));
    const REGION_COUNTRIES = new Set(['US', 'GB', 'AU', 'CA', 'DE', 'FR', 'ES', 'IT']);
    const POSTCODE_PLACEHOLDERS = {
        US: 'e.g., 94105',
        AU: 'e.g., 2000',
        GB: 'e.g., SW1A 1AA',
        CA: 'e.g., A1A 1A1',
        AT: 'e.g., 1010',
        BE: 'e.g., 1000',
        BG: 'e.g., 1000',
        HR: 'e.g., 10000',
        CY: 'e.g., 1010',
        CZ: 'e.g., 110 00',
        DK: 'e.g., 2100',
        EE: 'e.g., 10111',
        FI: 'e.g., 00100',
        FR: 'e.g., 75001',
        DE: 'e.g., 10115',
        GR: 'e.g., 105 58',
        HU: 'e.g., 1051',
        IE: 'e.g., D02 X285',
        IT: 'e.g., 20121',
        LV: 'e.g., LV-1050',
        LT: 'e.g., LT-01100',
        LU: 'e.g., L-1111',
        MT: 'e.g., VLT 1111',
        NL: 'e.g., 1012 AB',
        PL: 'e.g., 00-001',
        PT: 'e.g., 1100-150',
        RO: 'e.g., 010011',
        SK: 'e.g., 811 01',
        SI: 'e.g., 1000',
        ES: 'e.g., 28001',
        SE: 'e.g., 114 55',
        NO: 'e.g., 0150',
        CH: 'e.g., 8001',
        IS: 'e.g., 101',
        LI: 'e.g., 9490',
    };
    function fillRegionSelect(selectEl, countryCode, current){
        if(!selectEl) return;
        let list = [];
        if (countryCode === 'US') list = US_STATES;
        else if (countryCode === 'GB') list = GB_COUNTIES;
        else if (countryCode === 'AU') list = AU_STATES;
        else if (countryCode === 'CA') list = CA_PROVINCES;
        else if (countryCode === 'ES') list = ES_PROVINCES;
        else if (countryCode === 'IT') list = IT_PROVINCES;
        else if (countryCode === 'DE') list = DE_STATES;
        else if (countryCode === 'FR') list = FR_REGIONS;
        selectEl.innerHTML = `<option value="">${list.length ? 'Select...' : 'N/A'}</option>` + list.map(v=>`<option${(current && current.toLowerCase()===String(v).toLowerCase())?' selected':''}>${v}</option>`).join('');
        selectEl.disabled = list.length === 0;
    }
    function updateCountryDependentUI(prefix){
        const country = $(prefix+'Country').value;
        const regionEl = $(prefix+'Region');
        const current = regionEl.getAttribute('data-current-value') || '';
        const requiresRegion = REGION_COUNTRIES.has(country);
        fillRegionSelect(regionEl, country, current);
        const regionFieldEl = $(prefix+'RegionField');
        if (regionFieldEl) regionFieldEl.classList.toggle('hidden', !requiresRegion);
        if (!requiresRegion) regionEl.value = '';
        $(prefix+'RegionLabel').textContent = country==='US' ? 'State' : country==='GB' ? 'County' : country==='AU' ? 'State' : country==='CA' ? 'Province / Territory' : country==='ES' ? 'Provinces' : country==='IT' ? 'Province' : country==='DE' ? 'State' : country==='FR' ? 'Region' : 'State / County';
        const labelEl = $(prefix+'PostcodeLabel');
        const inputEl = $(prefix+'Postcode');
        labelEl.textContent = country==='US' ? 'ZIP' : 'Postcode';
        inputEl.placeholder = POSTCODE_PLACEHOLDERS[country] || 'e.g., 10115';
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

    const INITIAL_PAYMENT_ERROR = @json($paymentError ?? '');
    let paymentErrorSticky = false;

    // Wire DOM
    window.addEventListener('DOMContentLoaded', ()=>{
        if (INITIAL_PAYMENT_ERROR) {
            showFormAlert('danger', INITIAL_PAYMENT_ERROR);
            paymentErrorSticky = true;
            try { document.getElementById('formAlert')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) {}
        }

        // Payment method switch
        function setPayMethod(m){
            PAY_METHOD = m;
            const cardBtn=document.getElementById('pmCardBtn'); const zelleBtn=document.getElementById('pmZelleBtn'); const airwallexBtn=document.getElementById('pmAirwallexBtn');
            [cardBtn,zelleBtn,airwallexBtn].forEach(b=>{ if(!b) return; b.classList.remove('pm-active'); b.setAttribute('aria-selected','false'); });
            if(m==='card'){
                cardBtn?.classList.add('pm-active','bg-blue-600','text-white','border-blue-600');
                cardBtn?.classList.remove('border-blue-600/40','text-blue-700');
                zelleBtn?.classList.remove('bg-purple-600','text-white','border-purple-600');
                zelleBtn?.classList.add('border-purple-600/40','text-purple-700');
                airwallexBtn?.classList.remove('bg-teal-600','text-white','border-teal-600');
                airwallexBtn?.classList.add('border-teal-600/40','text-teal-700');
                cardBtn?.setAttribute('aria-selected','true');
                document.getElementById('airwallexNotice')?.classList.add('hidden');
                document.getElementById('airwallexPane')?.classList.add('hidden');
            } else if (m==='zelle') {
                zelleBtn?.classList.add('pm-active','bg-purple-600','text-white','border-purple-600');
                zelleBtn?.classList.remove('border-purple-600/40','text-purple-700');
                cardBtn?.classList.remove('bg-blue-600','text-white','border-blue-600');
                cardBtn?.classList.add('border-blue-600/40','text-blue-700');
                airwallexBtn?.classList.remove('bg-teal-600','text-white','border-teal-600');
                airwallexBtn?.classList.add('border-teal-600/40','text-teal-700');
                zelleBtn?.setAttribute('aria-selected','true');
                document.getElementById('airwallexNotice')?.classList.add('hidden');
                document.getElementById('airwallexPane')?.classList.add('hidden');
            } else if (m==='airwallex') {
                // Enforce EUR
                try {
                    if (currentCurr !== 'EUR') {
                        const eurBtn = document.querySelector('#currencySwitch .curr-btn[data-curr="EUR"]');
                        if (eurBtn) eurBtn.click();
                        else { showFormAlert('warning','SEPA is EUR-only and EUR is not available.'); PAY_METHOD='card'; return setPayMethod('card'); }
                    }
                } catch(_) {}
                airwallexBtn?.classList.add('pm-active','bg-teal-600','text-white','border-teal-600');
                airwallexBtn?.classList.remove('border-teal-600/40','text-teal-700');
                cardBtn?.classList.remove('bg-blue-600','text-white','border-blue-600');
                cardBtn?.classList.add('border-blue-600/40','text-blue-700');
                zelleBtn?.classList.remove('bg-purple-600','text-white','border-purple-600');
                zelleBtn?.classList.add('border-purple-600/40','text-purple-700');
                airwallexBtn?.setAttribute('aria-selected','true');
                document.getElementById('zelleNotice')?.classList.add('hidden');
                document.getElementById('airwallexPane')?.classList.add('hidden');
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
            // Show Airwallex pre-notice only when selected
            if (m==='airwallex') {
                document.getElementById('airwallexNotice')?.classList.remove('hidden');
                document.getElementById('airwallexPane')?.classList.add('hidden');
            } else {
                document.getElementById('airwallexNotice')?.classList.add('hidden');
                document.getElementById('airwallexPane')?.classList.add('hidden');
            }
        }
        document.getElementById('pmCardBtn')?.addEventListener('click',()=>setPayMethod('card'));
        document.getElementById('pmZelleBtn')?.addEventListener('click',()=>setPayMethod('zelle'));
        document.getElementById('pmAirwallexBtn')?.addEventListener('click',()=>setPayMethod('airwallex'));
        setPayMethod(PAY_METHOD);

        updatePayLabel();
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
                // Zelle/Venmo are US-only.
                updateUsOnlyMethodVisibility();
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

        // Copy buttons (Zelle) with fallback for non-secure contexts
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
                ta.style.pointerEvents = 'none';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                const ok = document.execCommand && document.execCommand('copy');
                document.body.removeChild(ta);
                return !!ok;
            } catch (_) { return false; }
        }
        (function(){
            document.querySelectorAll('.copy-btn').forEach(btn=>{
                btn.addEventListener('click', async ()=>{
                    const sel = btn.getAttribute('data-copy');
                    const el = sel && document.querySelector(sel);
                    if(!el) return;
                    const text = (el.value ?? el.textContent ?? '').toString();
                    const ok = await copyToClipboard(text);
                    try { announce(ok ? 'Copied to clipboard.' : 'Copy failed.'); } catch(_) {}
                    try { showToast(ok ? 'Copied to clipboard' : 'Copy failed'); } catch(_) {}
                });
            });
        })();

        // Submit handler
        $('checkoutForm').addEventListener('submit', async (e)=>{
            e.preventDefault();
            // quick validate + summary
            paymentErrorSticky = false;
            const requiredIds=['billEmail','billFirst','billLast','billCountry','billAddress1','billCity','billPostcode','billPhone'];
            if (PAY_METHOD==='card') requiredIds.push('card','exp','cvv');
            // Require region only for countries that explicitly use it.
            const billCountryVal = $('billCountry').value;
            if (REGION_COUNTRIES.has(billCountryVal)) {
                requiredIds.push('billRegion');
            }
            // If shipping section is visible, validate its fields; region required only for region countries.
            const shippingVisible = !$('shipSame').checked;
            if (shippingVisible) {
                requiredIds.push('shipFirst','shipLast','shipCountry','shipAddress1','shipCity','shipPostcode');
                const shipCountryVal = $('shipCountry').value;
                if (REGION_COUNTRIES.has(shipCountryVal)) {
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
            const phoneE164 = (window.itiBilling && typeof window.itiBilling.getNumber === 'function' && window.itiBilling.isValidNumber())
                ? window.itiBilling.getNumber()
                : $('billPhone').value.trim();

            let fpVisitorId = document.querySelector('input[name="payeasy_fp_visitor_id"]')?.value || '';
            let fpRequestId = document.querySelector('input[name="payeasy_fp_request_id"]')?.value || '';
            try {
                if (window.PayeasyFingerprint?.getResult) {
                    const fp = await window.PayeasyFingerprint.getResult();
                    fpVisitorId = fp?.visitorId || fpVisitorId;
                    fpRequestId = fp?.requestId || fpRequestId;
                }
            } catch (_) {}

            const payInfo = updateTotalsForPayMethod(false) || {};
            const payload = {
                email: $('billEmail').value,
                billingFirstname: $('billFirst').value,
                billingLastname: $('billLast').value,
                billingCountry: $('billCountry').value,
                billingAddress: $('billAddress1').value,
                billingCity: $('billCity').value,
                billingState: (function(){ const v=$('billRegion').value; const c=$('billCountry').value; return REGION_COUNTRIES.has(c) ? v : '-'; })(),
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
                currency: currentCurr,
                rate: currentRate,
                cardNumber: PAY_METHOD==='card' ? $('card').value : null,
                expiry:     PAY_METHOD==='card' ? $('exp').value  : null,
                cvc:        PAY_METHOD==='card' ? $('cvv').value  : null,
                pay_method: PAY_METHOD,
                fp_visitor_id: fpVisitorId || null,
                fp_request_id: fpRequestId || null,
                expected_amount: payInfo.totalStr ? parseFloat(payInfo.totalStr) : null,
                expected_currency: payInfo.payCurr || null,
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
                if (PAY_METHOD==='airwallex') {
                    if (!data.success) { showFormAlert('danger', data.message ?? 'Order failed. Please try again.'); return; }
                    AIRWALLEX_ORDER_PLACED = true; fillAirwallexFields(data);
                    document.getElementById('airwallexNotice')?.classList.remove('hidden');
                    document.getElementById('airwallexPane')?.classList.remove('hidden');
                    document.getElementById('payBtn')?.classList.add('hidden');
                    document.getElementById('airwallexPane')?.scrollIntoView({behavior:'smooth', block:'start'});
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

        // SEPA: invoice PDF + paid state
        document.addEventListener('click', (e)=>{
            if (e.target?.id === 'airwallexInvoiceBtn') {
                try {
                    const { jsPDF } = window.jspdf || {};
                    if (!jsPDF) return;
                    const doc = new jsPDF();
                    doc.setFontSize(16); doc.text('SEPA Invoice', 20, 20);
                    doc.setFontSize(11);
                    const lines = [
                        `Beneficiary: ${document.getElementById('airwallexBenef')?.value||''}`,
                        `IBAN: ${document.getElementById('airwallexIban')?.value||''}`,
                        `BIC: ${document.getElementById('airwallexBic')?.value||''}`,
                        `Amount: ${document.getElementById('airwallexAmount')?.value||''}`,
                        `Reference: ${document.getElementById('airwallexRef')?.value||''}`
                    ];
                    lines.forEach((l,i)=>doc.text(l,20,40+i*8));
                    doc.save('invoice.pdf');
                } catch(_) {}
            }
        });
        document.getElementById('airwallexPaidBtn')?.addEventListener('click',()=>{
            const btn = document.getElementById('airwallexPaidBtn');
            if (!btn) return;
            btn.disabled=true; btn.textContent='PROCESSING…';
            btn.classList.remove('bg-slate-800','hover:bg-slate-900');
            btn.classList.add('bg-slate-400','cursor-default');
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
            const row = document.getElementById('row-card');
            if (row) openRow(row);
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

        // Ensure card fields required/visible state is toggled consistently
        function setCardRequired(isCard){
            const details = document.getElementById('cardDetails');
            const cardEl = document.getElementById('card');
            const expEl = document.getElementById('exp');
            const cvvEl = document.getElementById('cvv');
            const errCard = document.getElementById('err-card');
            const errExp = document.getElementById('err-exp');
            const errCvv = document.getElementById('err-cvv');
            const cardPane = document.getElementById('cardPane');

            if (cardPane) cardPane.classList.toggle('hidden', !isCard);
            if (details) details.classList.toggle('hidden', !isCard);

            if (cardEl) {
                cardEl.toggleAttribute('required', isCard);
                if (!isCard) {
                    cardEl.setAttribute('aria-invalid','false');
                    if (errCard){ errCard.classList.add('hidden'); errCard.textContent=''; }
                    cardEl.classList.remove('border-red-500','ring-1','ring-red-300');
                }
            }
            if (expEl) {
                expEl.toggleAttribute('required', isCard);
                if (!isCard) {
                    expEl.setAttribute('aria-invalid','false');
                    if (errExp){ errExp.classList.add('hidden'); errExp.textContent=''; }
                    expEl.classList.remove('border-red-500','ring-1','ring-red-300');
                }
            }
            if (cvvEl) {
                cvvEl.toggleAttribute('required', isCard);
                if (!isCard) {
                    cvvEl.setAttribute('aria-invalid','false');
                    if (errCvv){ errCvv.classList.add('hidden'); errCvv.textContent=''; }
                    cvvEl.classList.remove('border-red-500','ring-1','ring-red-300');
                }
            }
        }

        function openRow(row){
            if (!row) return;
            const method = row.getAttribute('data-method');
            // if (method === 'airwallex'){
            //     if (currentCurr !== 'EUR'){
            //         const eurBtn = document.querySelector('#currencySwitch .curr-btn[data-curr="EUR"]');
            //         if (eurBtn) eurBtn.click(); else { showFormAlert('warning', 'Airwallex is EUR-only. EUR is not available for this order.'); return; }
            //     }
            // }
            document.querySelectorAll('.pm-row').forEach(r=>{
                const isOpen = r === row;
                r.setAttribute('aria-checked', isOpen ? 'true' : 'false');
                const content = r.querySelector('.pm-content');
                if (content) content.classList.toggle('open', isOpen);
            });
            PAY_METHOD = method;
            const isCard = PAY_METHOD === 'card';
            // Show/hide panes inside contents
            const cardPane = document.getElementById('cardPane');
            const cardDetails = document.getElementById('cardDetails');
            const zelleNotice = document.getElementById('zelleNotice');
            const zellePane = document.getElementById('zellePane');
            const airNotice = document.getElementById('airwallexNotice');
            const airPane = document.getElementById('airwallexPane');

            // Reset all to hidden by default
            if (cardPane) cardPane.classList.add('hidden');
            if (cardDetails) cardDetails.classList.add('hidden');
            if (zelleNotice) zelleNotice.classList.add('hidden');
            if (zellePane) zellePane.classList.add('hidden');
            if (airNotice) airNotice.classList.add('hidden');
            if (airPane) airPane.classList.add('hidden');

            if (isCard){
                if (cardPane) cardPane.classList.remove('hidden');
                if (cardDetails) cardDetails.classList.remove('hidden');
            } else if (PAY_METHOD === 'zelle'){
                if (ZELLE_ORDER_PLACED) {
                    if (zellePane) zellePane.classList.remove('hidden');
                } else {
                    if (zelleNotice) zelleNotice.classList.remove('hidden');
                }
            } else if (PAY_METHOD === 'airwallex'){
                if (AIRWALLEX_ORDER_PLACED) {
                    if (airPane) airPane.classList.remove('hidden');
                } else {
                    if (airNotice) airNotice.classList.remove('hidden');
                }
            }

            setCardRequired(isCard);
            // Reset Zelle UI only when switching AWAY from Zelle
            if (method !== 'zelle'){ ZELLE_ORDER_PLACED=false; resetZelleUI(); stopZelleTimer(); }
            refreshPayLabel();
            updateTotalsForPayMethod(false);
            try { row.scrollIntoView({behavior:'smooth',block:'start'}); } catch(_) {}
        }

        function updateUsOnlyMethodVisibility(){
            const country = document.getElementById('billCountry')?.value;
            const isUS = country === 'US';
            const zelleRow = document.getElementById('row-zelle');
            const venmoRow = document.getElementById('row-venmo');
            [zelleRow, venmoRow].forEach((row) => {
                if (row) row.classList.toggle('hidden', !isUS);
            });

            if (!isUS && (PAY_METHOD === 'zelle' || PAY_METHOD === 'venmo')) {
                const fallbackRow = document.getElementById('row-card') || document.querySelector('.pm-row:not(.hidden)');
                if (fallbackRow) {
                    const radio = fallbackRow.querySelector('input[type="radio"]');
                    if (radio) radio.checked = true;
                    openRow(fallbackRow);
                }
            }
        }

        // Bind row interactions (direct and delegated)
        document.querySelectorAll('.pm-row').forEach(r=>{
            r.addEventListener('click', e=>{ const radio = r.querySelector('input[type="radio"]'); if (radio) radio.checked = true; openRow(r); });
            r.addEventListener('keydown', e=>{ if (e.key==='Enter'||e.key===' '){ e.preventDefault(); const radio = r.querySelector('input[type="radio"]'); if (radio) radio.checked = true; openRow(r); }});
        });
        document.querySelectorAll('#shippingGroup input[name="shipping"]').forEach(r=> r.addEventListener('change',()=>updateTotalsForPayMethod(true)));
        document.addEventListener('click', (e)=>{
            // Ignore clicks within expanded content; only header area should toggle
            if (e.target.closest('.pm-content')) return;
            const row = e.target.closest('.pm-row');
            if (!row) return;
            const radio = row.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            openRow(row);
        });
        document.addEventListener('change', (e)=>{
            const radio = e.target && e.target.matches && e.target.matches('.pm-row input[type="radio"]') ? e.target : null;
            if (!radio) return;
            const row = radio.closest('.pm-row');
            if (row) openRow(row);
        });

        updateUsOnlyMethodVisibility();

        if (PAY_METHOD) {
            const defaultRow = document.querySelector(`.pm-row[data-method="${PAY_METHOD}"]:not(.hidden)`) || document.getElementById('row-card') || document.querySelector('.pm-row:not(.hidden)');

            if (defaultRow) openRow(defaultRow);
        }

        function refreshPayLabel(){
            try {
                const label = document.getElementById('payLabel');
                if (!label) return;
                const info = updateTotalsForPayMethod(false) || {};
                const display = `${info.paySymbol || ''}${info.totalStr || ''}`.trim();
                label.textContent = (PAY_METHOD === 'card')
                    ? `Pay ${display}`
                    : `Place order — ${display}`;
            } catch(_) {}
        }
    });
</script>
<!-- Google Maps Places (Autocomplete) -->
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initGooglePlaces" async defer></script>
</body>
</html>


