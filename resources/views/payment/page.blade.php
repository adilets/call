@extends('layouts.payment')

@section('content')
    <div class="container py-5">
        <div class="row">
            <div id="pay-alert" class="alert alert-warning alert-dismissible fade d-none" role="alert">
                <span class="alert-message">Processing…</span>

                <button type="button" class="btn-close" aria-label="Close" onclick="hideAlert()"></button>
            </div>

            <div class="col-md-6 mb-4">
                @php
                    // From controller: $currencies (USD, EUR), $selectedCurrency, $currencySymbols, $flagByCode
                    $selectedRate = (float)($currencies[$selectedCurrency] ?? 1.0);
                    $symbolForSelected = $currencySymbols[$selectedCurrency] ?? $selectedCurrency . ' ';
                @endphp

                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title">Select Currency</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach($currencies as $code => $rate)
                                @php
                                    $isActive = $code === $selectedCurrency;
                                    $symbol = $currencySymbols[$code] ?? $code;
                                    $flag = $flagByCode[$code] ?? null;
                                @endphp
                                <button class="btn btn-outline-primary d-flex align-items-center justify-content-center flex-grow-1 currency-btn {{ $isActive ? 'active' : '' }}"
                                    data-currency="{{ $code }}" data-rate="{{ number_format((float)$rate, 6, '.', '') }}" data-symbol="{{ $symbol }}">
                                    @if($flag)
                                        <img src="https://flagcdn.com/w20/{{ $flag }}.png" alt="{{ $code }}" class="me-2" width="20" height="20">
                                    @endif
                                    <span class="currency-code">{{ $code }}</span>
                                </button>
                            @endforeach
                        </div>
                        <small class="text-muted d-block mt-1">Exchange rates and bank fees may apply</small>
                    </div>
                </div>

                @php
                    /** @var \App\Models\Order|null $order */
                    $items = isset($order) ? ($order->items ?? collect()) : collect();
                    $subtotal = $items->sum(fn ($i) => (float) ($i->qty ?? 0) * (float) ($i->unit_price ?? 0));
                    $shippingMethods = isset($shippingMethods) ? $shippingMethods : \App\Models\ShippingMethod::query()->get();
                    $selectedShippingId = isset($order) ? ($order->shipping_method_id ?? null) : null;
                    $selectedShipping = $shippingMethods->firstWhere('id', $selectedShippingId) ?? $shippingMethods->first();
                    $shippingCost = (float) ($selectedShipping->cost ?? 0); // in USD
                    $currencySymbol = $symbolForSelected;
                @endphp

                <div class="card shadow mt-4">
                    <div class="card-body">
                        @forelse($items as $item)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="fs-6">
                                    {{ optional($item->product)->name ?? ('Product #' . ($item->product_id ?? '')) }}
                                    x {{ (int) ($item->qty ?? 0) }}
                                </span>
                                @php $lineUsd = (float) ($item->qty ?? 0) * (float) ($item->unit_price ?? 0); @endphp
                                <span class="fs-6 fw-bold product-line-price" data-usd-line-price="{{ number_format($lineUsd, 2, '.', '') }}">
                                    {{ $currencySymbol }}{{ number_format($lineUsd * $selectedRate, 2) }}
                                </span>
                            </div>
                        @empty
                            <div class="text-muted">No items in the order.</div>
                        @endforelse
                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Shipping Method</h5>
                        @foreach($shippingMethods as $method)
                            @php
                                $id = 'ship_' . $method->id;
                                $isChecked = $selectedShipping && $selectedShipping->id === $method->id;
                                $cost = (float) ($method->cost ?? 0);
                            @endphp
                            <div class="form-check {{ !$loop->first ? 'mt-2' : '' }}">
                                <input type="radio" class="form-check-input shipping-method" name="shipping" id="{{ $id }}" value="{{ number_format($cost, 2, '.', '') }}" data-method-id="{{ $method->id }}" {{ $isChecked ? 'checked' : '' }}>
                                <label class="form-check-label d-flex justify-content-between w-100" for="{{ $id }}">
                                    <span>{{ $method->name }}</span>
                                    <span>{{ $cost == 0 ? 'Free' : ($currencySymbol . number_format($cost, 2)) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between text-muted">
                            <span>Subtotal</span>
                            <span class="fw-bold subtotal" data-usd-subtotal="{{ number_format($subtotal, 2, '.', '') }}">{{ $currencySymbol }}{{ number_format($subtotal * $selectedRate, 2) }}</span>
                        </div>
                        {{-- <div class="d-flex justify-content-between text-success mt-2">
                            <span>Discount</span>
                            <span class="fw-bold discount">-{{ $currencySymbol }}0.00</span>
                        </div> --}}
                        <div class="d-flex justify-content-between text-muted mt-2">
                            <span>Shipping</span>
                            <span class="fw-bold shipping-cost" data-usd-shipping="{{ number_format($shippingCost, 2, '.', '') }}">{{ $shippingCost == 0 ? 'Free' : ($currencySymbol . number_format($shippingCost * $selectedRate, 2)) }}</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total</span>
                            @php $initialTotal = ($subtotal + $shippingCost) * $selectedRate; @endphp
                            <span class="total" data-selected-rate="{{ number_format($selectedRate, 6, '.', '') }}">{{ $currencySymbol }}{{ number_format($initialTotal, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- <div class="card shadow mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Add to Your Order</h5>
                        <button class="w-100 d-flex justify-content-between align-items-center bg-light border p-3 rounded mb-2 text-start add-item" data-price="0" data-name="Tadalafil sample pack x 5 pills, 50 ml">
                            <span class="flex-grow-1">Tadalafil sample pack x 5 pills, 50 ml</span>
                            <span class="me-2">Free</span>
                            <span class="text-primary fw-bold add-btn">+ Add</span>
                        </button>
                        <button class="w-100 d-flex justify-content-between align-items-center bg-light border p-3 rounded text-start add-item" data-price="95.50" data-name="Sildenafil sample pack x 30 pills, 100 ml">
                            <span class="flex-grow-1">Sildenafil sample pack x 30 pills, 100 ml</span>
                            <div class="text-end">
                                <span class="text-success fw-bold">$95.50</span>
                                <div class="d-flex align-items-center">
                                    <span class="strikethrough text-muted me-2">$125.50</span>
                                    <span class="discount-tag">SAVE 24%</span>
                                </div>
                            </div>
                            <span class="text-primary fw-bold add-btn">+ Add</span>
                        </button>
                    </div>
                </div> --}}
            </div>

            <div class="col-md-6">
                <div class="card shadow mb-4">
                    @php
                    $orderAddress = isset($order) ? ($order->address ?? null) : null;
                    $customerAddress = isset($order) ? optional(optional($order->customer)->addresses)->first() : null;
                    $billing = $orderAddress ?: $customerAddress;
                    $billingName = isset($order) ? optional($order->customer)->name : null;
                    $billingEmail = isset($order) ? optional($order->customer)->email : null;
                    $billingPhone = isset($order) ? optional($order->customer)->phone : null;
                    @endphp
                    <div class="card-body">
                        <h5 class="card-title">Billing Information</h5>
                        <input type="email" class="form-control mb-3" placeholder="Email" id="email" value="{{ $billingEmail }}">
                        <h6 class="mt-3">Billing Address</h6>
                        <input type="text" class="form-control mb-3" placeholder="Full name" id="fullName" value="{{ $billingName }}">
                        <select class="form-select mb-3" id="country">
                            <option value="">Select country</option>
                            @foreach(($countries ?? []) as $code => $name)
                                <option value="{{ $code }}" {{ strtoupper($billing?->country) === $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="form-control mb-3" placeholder="Address" id="address" value="{{ $billing?->street }}">
                        <div class="row">
                            <div class="col-6"><input type="text" class="form-control mb-3" placeholder="City" id="city" value="{{ $billing?->city }}"></div>
                            <div class="col-3"><input type="text" class="form-control mb-3" placeholder="State" id="state" value="{{ $billing?->state }}"></div>
                            <div class="col-3"><input type="text" class="form-control mb-3" placeholder="ZIP" id="zip" value="{{ $billing?->zip }}"></div>
                        </div>
                        <input type="tel" class="form-control mb-3" placeholder="Phone number (e.g., 070-123 45 67)" id="phone" value="{{ $billingPhone }}">
                        <div class="form-check mt-3">
                            <input type="checkbox" class="form-check-input" id="shippingSame" checked>
                            <label class="form-check-label" for="shippingSame">Shipping info is same as billing</label>
                        </div>
                        <div class="shipping-details mt-3" style="display: none;">
                            <h6>Shipping Details</h6>
                            <input type="text" class="form-control mb-3" placeholder="Full name" id="shippingFullName">
                            <select class="form-select mb-3" id="shippingCountry">
                                <option value="">Select country</option>
                                @foreach(($countries ?? []) as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="text" class="form-control mb-3" placeholder="Address" id="shippingAddress">
                            <div class="row">
                                <div class="col-6"><input type="text" class="form-control mb-3" placeholder="City" id="shippingCity"></div>
                                <div class="col-3"><input type="text" class="form-control mb-3" placeholder="State" id="shippingState"></div>
                                <div class="col-3"><input type="text" class="form-control mb-3" placeholder="ZIP" id="shippingZip"></div>
                            </div>
                            <input type="tel" class="form-control mb-3" placeholder="Phone number (e.g., 070-123 45 67)" id="shippingPhone">
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Payment Method</h5>
                        <div class="form-check">
                            <div class="mt-3 card-details">
                                <input type="text" class="form-control mb-3 card-number" placeholder="Card number 1234 1234 1234 1234" maxlength="19">
                                <div class="row">
                                    <div class="col-6">
                                        <input type="text" class="form-control expiry" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control cvc" placeholder="CVC" maxlength="4">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" height="20">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b7/MasterCard_Logo.svg" alt="Mastercard" height="20">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/f/fa/American_Express_logo_%282018%29.svg" alt="Amex" height="20">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="frame_uuid" id="frame_uuid" value="" />
                <input type="hidden" name="fl_sid" id="fl_sid" value="" />

                @php $buttonTotal = $initialTotal; @endphp
                <button class="btn btn-primary w-100 pay-btn" data-symbol="{{ $currencySymbol }}">Pay {{ $currencySymbol }}{{ number_format($buttonTotal, 2) }}</button>

                <div class="text-center text-muted mt-3">
                    <p class="small">We use 256-bit encryption to protect your data.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAlert(type = 'warning', message = 'Notice') {
            const el = document.getElementById('pay-alert');
            if (!el) return;

            // Сброс классов и показ
            el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
            el.classList.add(`alert-${type}`, 'show');

            // Текст
            el.querySelector('.alert-message').textContent = message;
        }

        function hideAlert() {
            const el = document.getElementById('pay-alert');
            if (!el) return;
            el.classList.add('d-none');
        }

        //Scoring script
        (function(){
            const opts = {
                clientId: 'bf15fe',
                endpoint: 'https://webanalytic.app',
                fieldId: 'frame_uuid',
                sidFieldId: 'fl_sid',
                cookieName: 'fbl_cookie_id',
                years: 100,
            };

            // --- helpers ---
            const uuid = () => crypto.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{
                const r = crypto.getRandomValues(new Uint8Array(1))[0] & 15;
                return (c==='x'?r:(r&0x3|0x8)).toString(16);
            });

            const getCookie = n => ('; '+document.cookie).split(`; ${n}=`).pop().split(';')[0] || null;
            const setCookie = v => {
                const d=new Date(); d.setFullYear(d.getFullYear()+opts.years);
                document.cookie=`${opts.cookieName}=${v}; Path=/; Expires=${d.toUTCString()}; SameSite=None; Secure`;
                return v;
            };

            // --- init ---
            const sid = getCookie(opts.cookieName) || setCookie(uuid() + ':' + Date.now());
            const id  = uuid();


            document.getElementById(opts.fieldId)?.setAttribute('value', id);
            document.getElementById(opts.sidFieldId)?.setAttribute('value', sid);

            const ud = [
                screen.width, screen.height, screen.colorDepth,
                devicePixelRatio, new Date().getTimezoneOffset(),
                navigator.platform, new Date().toISOString()
            ];
            try {ud.push(Intl.DateTimeFormat().resolvedOptions().timeZone)}catch{ud.push('-')}

            const url = `${opts.endpoint}/transactions/${opts.clientId}/${id}?cid=${sid}&uv1=${encodeURIComponent(JSON.stringify(ud))}`;
            const iframe = Object.assign(document.createElement('iframe'), {src:url,width:0,height:0,style:'border:0',referrerPolicy:'no-referrer'});
            document.body.appendChild(iframe);
        })();
        // End scoring script

        document.getElementById('shippingSame').addEventListener('change', function () {
            document.querySelector('.shipping-details').style.display = this.checked ? 'none' : 'block';
        });

        // Currency selection: recalc amounts based on USD base and selected rate
        const currencyBtns = document.querySelectorAll('.currency-btn');
        let currentRate = parseFloat(document.querySelector('.total').getAttribute('data-selected-rate')) || 1.0;
        let currentSymbol = document.querySelector('.pay-btn').getAttribute('data-symbol') || '$';

        currencyBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                currencyBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                currentRate = parseFloat(this.getAttribute('data-rate')) || 1.0;
                currentSymbol = this.getAttribute('data-symbol') || '$';

                // Update line items
                document.querySelectorAll('.product-line-price').forEach(el => {
                    const usd = parseFloat(el.getAttribute('data-usd-line-price')) || 0;
                    el.textContent = `${currentSymbol}${(usd * currentRate).toFixed(2)}`;
                });

                // Update subtotal
                const subtotalEl = document.querySelector('.subtotal');
                const usdSubtotal = parseFloat(subtotalEl.getAttribute('data-usd-subtotal')) || 0;
                subtotalEl.textContent = `${currentSymbol}${(usdSubtotal * currentRate).toFixed(2)}`;

                // Update shipping (keeping current selected method cost in USD)
                const shippingEl = document.querySelector('.shipping-cost');
                const usdShipping = parseFloat(shippingEl.getAttribute('data-usd-shipping')) || 0;
                shippingEl.textContent = usdShipping === 0 ? 'Free' : `${currentSymbol}${(usdShipping * currentRate).toFixed(2)}`;

                // Update total and button
                updateGrandTotal();
            });
        });

        const addItems = document.querySelectorAll('.add-item');
        addItems.forEach(item => {
            item.addEventListener('click', function () {
                const btn = this.querySelector('.add-btn');
                const price = parseFloat(this.dataset.price);
                if (btn.textContent === '+ Add') {
                    btn.textContent = '- Remove';
                    updateTotal(price, 'add');
                } else {
                    btn.textContent = '+ Add';
                    updateTotal(price, 'remove');
                }
            });
        });

        const shippingMethods = document.querySelectorAll('.shipping-method');
        shippingMethods.forEach(method => {
            method.addEventListener('change', function () {
                updateShipping(parseFloat(this.value));
            });
        });

        const payBtn = document.querySelector('.pay-btn');
        payBtn.addEventListener('click', function () {
            const formData = {
                email: document.getElementById('email').value,
                billingFullName: document.getElementById('fullName').value,
                billingCountry: document.getElementById('country').value,
                billingAddress: document.getElementById('address').value,
                billingCity: document.getElementById('city').value,
                billingState: document.getElementById('state').value,
                billingZip: document.getElementById('zip').value,
                billingPhone: document.getElementById('phone').value,
                shippingFullName: document.getElementById('shippingFullName')?.value,
                shippingCountry: document.getElementById('shippingCountry')?.value,
                shippingAddress: document.getElementById('shippingAddress')?.value,
                shippingCity: document.getElementById('shippingCity')?.value,
                shippingState: document.getElementById('shippingState')?.value,
                shippingZip: document.getElementById('shippingZip')?.value,
                shippingPhone: document.getElementById('shippingPhone')?.value,
                shippingMethod: document.querySelector('input[name="shipping"]:checked').value,
                cardNumber: document.querySelector('.card-number').value,
                expiry: document.querySelector('.expiry').value,
                cvc: document.querySelector('.cvc').value,
                fl_sid: document.getElementById('fl_sid')?.value,
                frame_uuid: document.getElementById('frame_uuid')?.value
            };

            // Валидация (пример)
            if (!formData.email || !formData.billingFullName || !formData.billingAddress || !formData.billingPhone) {
                showAlert('danger', 'Please fill in all required billing fields.');
                return;
            }

            if (!formData.cardNumber || !formData.expiry || !formData.cvc) {
                showAlert('danger', 'Please fill in all card details.');
                return;
            }

            payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing...';
            payBtn.disabled = true;

            const token = window.location.pathname.split('/').pop();
            fetch(`/pay/${token}/process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    ...formData,
                    shippingSame: document.getElementById('shippingSame').checked,
                    currency: document.querySelector('.currency-btn.active')?.getAttribute('data-currency') || 'USD',
                    shipping_method_id: document.querySelector('input[name="shipping"]:checked')?.getAttribute('data-method-id') || null,
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        showAlert('danger', data.message ?? 'Payment failed. Please verify your card details or try again later.');

                        return;
                    }

                    const token = window.location.pathname.split('/').pop();
                    const url   = `/pay/${token}/thank-you`;

                    window.location.replace(url);
                })
                .catch(error => {
                    showAlert('warning', 'An error occurred during payment processing.');
                })
                .finally(() => {
                    payBtn.innerHTML = `Pay ${document.querySelector('.total').textContent}`;
                    payBtn.disabled = false;
                });
        });

        const cardNumberInput = document.querySelector('.card-number');
        cardNumberInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').replace(/(\d{4})(?=\d)/g, '$1 ').trim();
            if (this.value.length > 19) this.value = this.value.slice(0, 19);
        });

        const expiryInput = document.querySelector('.expiry');
        expiryInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').replace(/(\d{2})(\d{0,2})/, '$1/$2').slice(0, 5);
        });

        const cvcInput = document.querySelector('.cvc');
        cvcInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });

        function updateTotal(price, action) {
            let subtotal = parseFloat(document.querySelector('.subtotal').textContent.replace('$', ''));
            subtotal = action === 'add' ? subtotal + price : subtotal - price;
            document.querySelector('.subtotal').textContent = `$${subtotal.toFixed(2)}`;
            updateGrandTotal();
        }

        function updateShipping(costUsd) {
            const el = document.querySelector('.shipping-cost');
            el.setAttribute('data-usd-shipping', parseFloat(costUsd).toFixed(2));
            el.textContent = parseFloat(costUsd) === 0 ? 'Free' : `${currentSymbol}${(parseFloat(costUsd) * currentRate).toFixed(2)}`;
            updateGrandTotal();
        }

        function updateGrandTotal() {
            const usdSubtotal = parseFloat(document.querySelector('.subtotal').getAttribute('data-usd-subtotal')) || 0;
            const usdShipping = parseFloat(document.querySelector('.shipping-cost').getAttribute('data-usd-shipping')) || 0;
            const total = (usdSubtotal + usdShipping) * currentRate;
            document.querySelector('.total').textContent = `${currentSymbol}${total.toFixed(2)}`;
            const btn = document.querySelector('.pay-btn');
            btn.textContent = `Pay ${currentSymbol}${total.toFixed(2)}`;
            btn.setAttribute('data-symbol', currentSymbol);
        }
    </script>
@endsection
