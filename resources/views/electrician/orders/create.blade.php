@extends('layouts.electrician')

@section('title', __('Create order for customer'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('Create order (on behalf of customer)') }}</h1>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Choose your shop, the customer, shipping address, and products. You will be recorded as the electrician on this order.') }}
        </p>
    </div>

    <form method="post" action="{{ route('electrician.orders.store') }}" class="space-y-6" id="electrician-order-form">
        @csrf

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <p class="text-sm font-semibold">{{ __('Please fix the following errors:') }}</p>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Order details') }}</h2>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div>
                    <label for="shop_id" class="block text-sm font-medium text-gray-700">{{ __('Shop') }} *</label>
                    <select name="shop_id" id="shop_id" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">{{ __('Select shop') }}</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                    @error('shop_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="relative">
                    <label for="customer_search" class="block text-sm font-medium text-gray-700">{{ __('Find customer') }} *</label>
                    <input type="text" id="customer_search" name="customer_search" autocomplete="off" placeholder="{{ __('Name, email, or phone…') }}"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        value="{{ old('customer_search') }}">
                    <input type="hidden" name="user_id" id="user_id" value="{{ old('user_id') }}" required>
                    <div id="customer_results" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>
                    <p id="customer_hint" class="mt-2 text-sm text-gray-500"></p>
                    @error('user_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address_id" class="block text-sm font-medium text-gray-700">{{ __('Address') }}</label>
                    <select name="address_id" id="address_id"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">{{ __('Select customer first') }}</option>
                    </select>
                    @error('address_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 mb-4">
                <h2 class="text-lg font-medium text-gray-900">{{ __('Products') }}</h2>
                <button type="button" id="add-line"
                    class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">
                    {{ __('Add line') }}
                </button>
            </div>

            <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <label for="ai_prompt" class="block text-sm font-medium text-gray-800">{{ __('Search & add products using AI') }}</label>
                <p class="mt-1 text-xs text-gray-600">{{ __('Example: Add 2 Havells 5A MCB and 1 Finolex 1.5mm wire') }}</p>
                <div class="mt-2 flex gap-2">
                    <textarea id="ai_prompt" rows="2"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        placeholder="{{ __('Type what you want to add') }}"></textarea>
                    <button type="button" id="apply-ai-items"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-70">
                        <svg id="ai_spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="4"></circle>
                            <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                        </svg>
                        <span id="ai_btn_label">{{ __('Search & add') }}</span>
                    </button>
                </div>
                <p id="ai_message" class="mt-2 text-xs text-gray-700"></p>
            </div>

            @error('items')
                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="overflow-x-auto overflow-y-visible">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-4">{{ __('Product') }}</th>
                            <th class="py-2 pr-4">{{ __('Variant') }}</th>
                            <th class="py-2 pr-4 w-24">{{ __('Qty') }}</th>
                            <th class="py-2 w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="lines-body" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ __('Products are limited to the shop you selected.') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-amber-700">
                {{ __('Create order') }}
            </button>
            <a href="{{ route('electrician.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const shopSelect = document.getElementById('shop_id');
    const customerSearch = document.getElementById('customer_search');
    const userIdInput = document.getElementById('user_id');
    const customerResults = document.getElementById('customer_results');
    const customerHint = document.getElementById('customer_hint');
    const addressSelect = document.getElementById('address_id');
    const linesBody = document.getElementById('lines-body');
    const addLineBtn = document.getElementById('add-line');
    const aiPrompt = document.getElementById('ai_prompt');
    const aiApplyBtn = document.getElementById('apply-ai-items');
    const aiMessage = document.getElementById('ai_message');
    const aiSpinner = document.getElementById('ai_spinner');
    const aiBtnLabel = document.getElementById('ai_btn_label');

    let lineIndex = 0;
    let customerTimer = null;

    const routes = {
        customers: @json(route('electrician.orders.search-customers')),
        products: @json(route('electrician.orders.search-products')),
        aiSuggest: @json(route('electrician.orders.ai-suggest')),
        addresses: (id) => @json(url('/electrician/orders/customers')) + '/' + id + '/addresses',
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (window.axios && csrfToken) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    }

    async function httpGet(url, params) {
        if (window.axios && typeof window.axios.get === 'function') {
            const res = await window.axios.get(url, { params });
            return res.data;
        }

        const qs = new URLSearchParams(params || {}).toString();
        const target = qs ? `${url}?${qs}` : url;
        const res = await fetch(target, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error('Request failed');
        }

        return await res.json();
    }

    function debounce(fn, ms) {
        return function (...args) {
            clearTimeout(customerTimer);
            customerTimer = setTimeout(() => fn.apply(this, args), ms);
        };
    }

    customerSearch.addEventListener('input', debounce(function () {
        const q = customerSearch.value.trim();
        customerResults.classList.add('hidden');
        customerResults.innerHTML = '';
        if (q.length < 2) {
            return;
        }
        httpGet(routes.customers, { q }).then(function (json) {
            const rows = json.data || [];
            if (!rows.length) {
                customerResults.innerHTML = '<div class="px-3 py-3 text-sm text-gray-600">' + escapeHtml('{{ __("No customers match your search.") }}') + '</div>';
                customerResults.classList.remove('hidden');
                return;
            }
            customerResults.innerHTML = rows.map(function (u) {
                return '<button type="button" class="customer-pick block w-full px-3 py-2 text-left text-sm hover:bg-amber-50" data-id="' + u.id + '">' + escapeHtml(u.label) + '</button>';
            }).join('');
            customerResults.classList.remove('hidden');
        }).catch(function () {
            customerResults.innerHTML = '<div class="px-3 py-3 text-sm text-red-600">' + escapeHtml('{{ __("Could not search customers. Try again.") }}') + '</div>';
            customerResults.classList.remove('hidden');
        });
    }, 300));

    customerResults.addEventListener('click', function (e) {
        const btn = e.target.closest('.customer-pick');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        userIdInput.value = id;
        customerSearch.value = btn.textContent.replace(/\s+/g, ' ').trim();
        customerResults.classList.add('hidden');
        customerHint.textContent = '{{ __("Customer selected.") }}';
        loadAddresses(id);
    });

    document.addEventListener('click', function (e) {
        if (!customerResults.contains(e.target) && e.target !== customerSearch) {
            customerResults.classList.add('hidden');
        }
    });

    function loadAddresses(customerId, selectedAddressId = null) {
        addressSelect.innerHTML = '<option value="">{{ __("Loading…") }}</option>';
        httpGet(routes.addresses(customerId)).then(function (json) {
            const list = json.data || [];
            addressSelect.innerHTML = '<option value="">{{ __("No address (optional)") }}</option>';
            list.forEach(function (a) {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = (a.label ? a.label + ' — ' : '') + (a.line || '');
                if (selectedAddressId !== null && String(selectedAddressId) === String(a.id)) {
                    opt.selected = true;
                }
                addressSelect.appendChild(opt);
            });
        }).catch(function () {
            addressSelect.innerHTML = '<option value="">{{ __("Could not load addresses") }}</option>';
        });
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    let activeProductDropdown = null;

    function positionProductDropdown(inputEl, ddEl) {
        const r = inputEl.getBoundingClientRect();
        const margin = 8;
        const width = Math.min(Math.max(r.width, 220), window.innerWidth - margin * 2);
        let left = r.left;
        if (left + width > window.innerWidth - margin) {
            left = window.innerWidth - margin - width;
        }
        if (left < margin) {
            left = margin;
        }
        ddEl.style.position = 'fixed';
        ddEl.style.left = left + 'px';
        ddEl.style.top = (r.bottom + 4) + 'px';
        ddEl.style.width = width + 'px';
        ddEl.style.zIndex = '60';
    }

    function repositionActiveProductDropdown() {
        if (activeProductDropdown && !activeProductDropdown.dd.classList.contains('hidden')) {
            positionProductDropdown(activeProductDropdown.input, activeProductDropdown.dd);
        }
    }

    function hideProductDropdown(dd) {
        dd.classList.add('hidden');
        dd.innerHTML = '';
        if (activeProductDropdown && activeProductDropdown.dd === dd) {
            activeProductDropdown = null;
        }
    }

    window.addEventListener('resize', repositionActiveProductDropdown);
    document.addEventListener('scroll', repositionActiveProductDropdown, true);

    document.addEventListener('click', function (e) {
        if (e.target.closest('.product-search') || e.target.closest('.product-dropdown')) {
            return;
        }
        document.querySelectorAll('.product-dropdown').forEach(function (el) {
            hideProductDropdown(el);
        });
    });

    function addLine() {
        const idx = lineIndex++;
        const tr = document.createElement('tr');
        tr.dataset.lineIndex = String(idx);
        tr._productById = {};
        tr.innerHTML = `
            <td class="py-3 pr-4 align-top">
                <input type="text" class="product-search w-full min-w-[200px] rounded border border-gray-300 px-2 py-1.5 text-sm" placeholder="{{ __('Search product…') }}" autocomplete="off">
                <input type="hidden" name="items[${idx}][product_id]" class="product-id" value="">
                <div class="product-dropdown hidden max-h-48 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg" aria-live="polite"></div>
            </td>
            <td class="py-3 pr-4 align-top">
                <select name="items[${idx}][product_variant_id]" class="variant-select w-full min-w-[140px] rounded border border-gray-300 px-2 py-1.5 text-sm"></select>
            </td>
            <td class="py-3 pr-4 align-top">
                <input type="number" name="items[${idx}][quantity]" min="1" value="1" class="qty w-20 rounded border border-gray-300 px-2 py-1.5 text-sm" required>
            </td>
            <td class="py-3 align-top">
                <button type="button" class="remove-line text-red-600 hover:text-red-800 text-sm">{{ __('Remove') }}</button>
            </td>`;
        linesBody.appendChild(tr);

        const searchInput = tr.querySelector('.product-search');
        const hiddenPid = tr.querySelector('.product-id');
        const dd = tr.querySelector('.product-dropdown');
        const variantSelect = tr.querySelector('.variant-select');

        let productTimer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(productTimer);
            const q = searchInput.value.trim();
            hideProductDropdown(dd);
            hiddenPid.value = '';
            variantSelect.innerHTML = '';
            const shopId = shopSelect.value;
            if (!shopId || q.length < 2) {
                return;
            }
            productTimer = setTimeout(function () {
                httpGet(routes.products, { shop_id: shopId, q }).then(function (json) {
                    document.querySelectorAll('.product-dropdown').forEach(function (el) {
                        if (el !== dd) {
                            hideProductDropdown(el);
                        }
                    });
                    const products = json.data || [];
                    if (!products.length) {
                        dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-600">' + escapeHtml('{{ __("No products match your search.") }}') + '</div>';
                        activeProductDropdown = { input: searchInput, dd: dd };
                        positionProductDropdown(searchInput, dd);
                        dd.classList.remove('hidden');
                        return;
                    }
                    tr._productById = {};
                    products.forEach(function (p) {
                        tr._productById[p.id] = p;
                    });
                    dd.innerHTML = products.map(function (p) {
                        return '<button type="button" class="product-pick block w-full px-3 py-2 text-left text-sm hover:bg-amber-50" data-product-id="' + p.id + '">' + escapeHtml(p.name) + (p.brand ? ' <span class="text-gray-500">(' + escapeHtml(p.brand) + ')</span>' : '') + '</button>';
                    }).join('');
                    activeProductDropdown = { input: searchInput, dd: dd };
                    positionProductDropdown(searchInput, dd);
                    dd.classList.remove('hidden');
                }).catch(function () {
                    document.querySelectorAll('.product-dropdown').forEach(function (el) {
                        if (el !== dd) {
                            hideProductDropdown(el);
                        }
                    });
                    dd.innerHTML = '<div class="px-3 py-3 text-sm text-red-600">' + escapeHtml('{{ __("Could not search products. Try again.") }}') + '</div>';
                    activeProductDropdown = { input: searchInput, dd: dd };
                    positionProductDropdown(searchInput, dd);
                    dd.classList.remove('hidden');
                });
            }, 300);
        });

        dd.addEventListener('click', function (e) {
            const b = e.target.closest('.product-pick');
            if (!b) return;
            const pid = parseInt(b.getAttribute('data-product-id'), 10);
            const p = tr._productById[pid];
            if (!p) return;
            hiddenPid.value = p.id;
            searchInput.value = p.name;
            hideProductDropdown(dd);
            variantSelect.innerHTML = '';
            const vars = p.variants || [];
            if (vars.length === 1) {
                const opt = document.createElement('option');
                opt.value = vars[0].id;
                opt.textContent = vars[0].label + ' — ₹' + vars[0].price;
                variantSelect.appendChild(opt);
            } else if (vars.length > 1) {
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = '{{ __("Choose variant") }}';
                variantSelect.appendChild(empty);
                vars.forEach(function (v) {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = v.label + ' — ₹' + v.price + (v.stock != null ? ' (stock ' + v.stock + ')' : '');
                    variantSelect.appendChild(opt);
                });
            }
        });

        tr.querySelector('.remove-line').addEventListener('click', function () {
            tr.remove();
        });
    }

    function addPresetLine(item) {
        addLine();
        const tr = linesBody.lastElementChild;
        if (!tr) {
            return;
        }
        const searchInput = tr.querySelector('.product-search');
        const hiddenPid = tr.querySelector('.product-id');
        const variantSelect = tr.querySelector('.variant-select');
        const qtyInput = tr.querySelector('.qty');

        hiddenPid.value = item.product_id;
        searchInput.value = item.product_name + (item.brand ? ' (' + item.brand + ')' : '');
        qtyInput.value = item.quantity || 1;
        variantSelect.innerHTML = '';
        const variants = Array.isArray(item.variants) ? item.variants : [];
        if (variants.length > 1) {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '{{ __("Choose variant") }}';
            variantSelect.appendChild(empty);
        }

        if (variants.length) {
            variants.forEach(function (v) {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = v.label + ' — ₹' + v.price + (v.stock != null ? ' (stock ' + v.stock + ')' : '');
                if (String(v.id) === String(item.variant_id)) {
                    opt.selected = true;
                }
                variantSelect.appendChild(opt);
            });
        } else {
            const opt = document.createElement('option');
            opt.value = item.variant_id;
            opt.textContent = item.variant_label + ' — ₹' + item.price + (item.stock != null ? ' (stock ' + item.stock + ')' : '');
            opt.selected = true;
            variantSelect.appendChild(opt);
        }
    }

    function removeEmptyLines() {
        const rows = Array.from(linesBody.querySelectorAll('tr'));
        rows.forEach(function (tr) {
            const productId = tr.querySelector('.product-id')?.value?.trim() || '';
            const productSearch = tr.querySelector('.product-search')?.value?.trim() || '';
            const variantVal = tr.querySelector('.variant-select')?.value?.trim() || '';
            const qtyVal = tr.querySelector('.qty')?.value?.trim() || '1';
            const isDefaultQty = qtyVal === '' || qtyVal === '1';
            const isEmpty = !productId && !productSearch && !variantVal && isDefaultQty;
            if (isEmpty) {
                tr.remove();
            }
        });
    }

    addLineBtn.addEventListener('click', function () {
        if (!shopSelect.value) {
            alert('{{ __("Please select a shop first.") }}');
            return;
        }
        addLine();
    });

    aiApplyBtn.addEventListener('click', async function () {
        const prompt = (aiPrompt.value || '').trim();
        const shopId = shopSelect.value;
        aiMessage.textContent = '';

        if (!shopId) {
            aiMessage.textContent = '{{ __("Select a shop first.") }}';
            aiMessage.className = 'mt-2 text-xs text-red-700';
            return;
        }
        if (!prompt) {
            aiMessage.textContent = '{{ __("Type what you want to add.") }}';
            aiMessage.className = 'mt-2 text-xs text-red-700';
            return;
        }

        try {
            aiApplyBtn.disabled = true;
            aiSpinner.classList.remove('hidden');
            aiBtnLabel.textContent = '{{ __("Searching...") }}';
            const res = await fetch(routes.aiSuggest, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ shop_id: Number(shopId), prompt }),
            });

            const json = await res.json();
            if (!res.ok) {
                const msg = json?.message || json?.errors?.prompt?.[0] || '{{ __("Could not process AI input.") }}';
                aiMessage.textContent = msg;
                aiMessage.className = 'mt-2 text-xs text-red-700';
                return;
            }

            const items = json.data || [];
            const missing = json.missing || [];

            if (items.length) {
                removeEmptyLines();
                aiPrompt.value = '';
            }
            items.forEach(addPresetLine);

            let msg = items.length
                ? `{{ __('Added') }} ${items.length} {{ __('item(s).') }}`
                : '{{ __("No products matched your request.") }}';
            if (missing.length) {
                msg += ' {{ __("Not found:") }} ' + missing.join(', ');
            }
            aiMessage.textContent = msg;
            aiMessage.className = items.length ? 'mt-2 text-xs text-green-700' : 'mt-2 text-xs text-red-700';
        } catch (e) {
            aiMessage.textContent = '{{ __("AI request failed. Please try again.") }}';
            aiMessage.className = 'mt-2 text-xs text-red-700';
        } finally {
            aiApplyBtn.disabled = false;
            aiSpinner.classList.add('hidden');
            aiBtnLabel.textContent = '{{ __("Search & add") }}';
        }
    });

    shopSelect.addEventListener('change', function () {
        linesBody.innerHTML = '';
        lineIndex = 0;
        if (shopSelect.value) {
            addLine();
        }
    });

    @if (old('user_id'))
        customerHint.textContent = '{{ __("Customer restored from previous attempt.") }}';
        loadAddresses(@json((int) old('user_id')), @json(old('address_id')));
    @endif

    if (linesBody.children.length === 0) {
        addLine();
    }
})();
</script>
@endpush
