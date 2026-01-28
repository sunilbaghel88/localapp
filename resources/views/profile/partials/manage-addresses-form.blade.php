<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Shipping Addresses') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Manage your shipping addresses for faster checkout.') }}
        </p>
    </header>

    @if(session('status') === 'address-created')
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ __('Address created successfully.') }}
        </div>
    @endif

    @if(session('status') === 'address-updated')
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ __('Address updated successfully.') }}
        </div>
    @endif

    @if(session('status') === 'address-deleted')
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ __('Address deleted successfully.') }}
        </div>
    @endif

    @if(session('status') === 'address-set-default')
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ __('Default address updated successfully.') }}
        </div>
    @endif

    <div class="mt-6 space-y-4">
        <!-- Existing Addresses -->
        @if($addresses->count() > 0)
            <div class="space-y-3">
                @foreach($addresses as $address)
                    <div class="p-4 border-2 rounded-lg {{ $address->is_default ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                @if($address->is_default)
                                    <span class="inline-block bg-amber-500 text-white text-xs font-semibold px-2 py-1 rounded mb-2">Default</span>
                                @endif
                                @if($address->label)
                                    <span class="inline-block bg-gray-200 text-gray-700 text-xs font-semibold px-2 py-1 rounded mb-2">{{ $address->label }}</span>
                                @endif
                                <div class="mt-2">
                                    <div class="font-semibold text-gray-900">{{ $address->name }}</div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        {{ $address->address_line1 }}<br>
                                        @if($address->address_line2){{ $address->address_line2 }}<br>@endif
                                        {{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}<br>
                                        {{ $address->country }}
                                    </div>
                                    @if($address->phone)
                                    <div class="text-sm text-gray-600 mt-1">Phone: {{ $address->phone }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-2 ml-4">
                                @if(!$address->is_default)
                                    <form method="POST" action="{{ route('profile.addresses.set-default', $address) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-amber-600 hover:text-amber-700 font-medium">
                                            Set Default
                                        </button>
                                    </form>
                                @endif
                                <button type="button" onclick="editAddress({{ $address->id }})" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('profile.addresses.destroy', $address) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-700 font-medium">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-600 text-sm">No addresses found. Add your first address below.</p>
        @endif

        <!-- Add/Edit Address Form -->
        <div class="mt-6">
            <button type="button" onclick="toggleAddressForm()" id="toggle-form-btn" class="text-amber-600 hover:text-amber-700 font-medium">
                + Add New Address
            </button>

            <div id="address-form-container" class="hidden mt-4 p-4 border-2 border-gray-200 rounded-lg bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900 mb-4" id="form-title">Add New Address</h3>
                
                <form method="POST" id="address-form" action="{{ route('profile.addresses.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" id="address-id" name="address_id" value="">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="label" class="block text-sm font-medium text-gray-700 mb-1">Label (e.g., Home, Work)</label>
                            <input type="text" id="label" name="label" value="{{ old('label') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div>
                        <label for="address_line1" class="block text-sm font-medium text-gray-700 mb-1">Address Line 1 <span class="text-red-500">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" value="{{ old('address_line1') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <x-input-error class="mt-2" :messages="$errors->get('address_line1')" />
                    </div>

                    <div>
                        <label for="address_line2" class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                        <input type="text" id="address_line2" name="address_line2" value="{{ old('address_line2') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                            <input type="text" id="city" name="city" value="{{ old('city') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-input-error class="mt-2" :messages="$errors->get('city')" />
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
                            <input type="text" id="state" name="state" value="{{ old('state') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-input-error class="mt-2" :messages="$errors->get('state')" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                            <input type="text" id="country" name="country" value="{{ old('country') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-input-error class="mt-2" :messages="$errors->get('country')" />
                        </div>
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">Postal Code <span class="text-red-500">*</span></label>
                            <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="is_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                        <label for="is_default" class="ml-2 block text-sm text-gray-700">Set as default address</label>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-md font-medium transition">
                            <span id="submit-text">Add Address</span>
                        </button>
                        <button type="button" onclick="cancelAddressForm()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md font-medium transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
const addresses = @json($addresses->keyBy('id'));

function toggleAddressForm() {
    const container = document.getElementById('address-form-container');
    const btn = document.getElementById('toggle-form-btn');
    
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        btn.textContent = '− Cancel';
        resetForm();
    } else {
        container.classList.add('hidden');
        btn.textContent = '+ Add New Address';
        resetForm();
    }
}

function cancelAddressForm() {
    document.getElementById('address-form-container').classList.add('hidden');
    document.getElementById('toggle-form-btn').textContent = '+ Add New Address';
    resetForm();
}

function editAddress(addressId) {
    const address = addresses[addressId];
    if (!address) return;

    // Populate form
    document.getElementById('address-id').value = address.id;
    document.getElementById('label').value = address.label || '';
    document.getElementById('name').value = address.name || '';
    document.getElementById('phone').value = address.phone || '';
    document.getElementById('address_line1').value = address.address_line1 || '';
    document.getElementById('address_line2').value = address.address_line2 || '';
    document.getElementById('city').value = address.city || '';
    document.getElementById('state').value = address.state || '';
    document.getElementById('country').value = address.country || '';
    document.getElementById('postal_code').value = address.postal_code || '';
    document.getElementById('is_default').checked = address.is_default || false;

    // Update form action and method
    const form = document.getElementById('address-form');
    form.action = '{{ route("profile.addresses.update", ":id") }}'.replace(':id', address.id);
    
    // Remove existing method override if any
    const existingMethod = form.querySelector('input[name="_method"]');
    if (existingMethod) {
        existingMethod.remove();
    }
    
    // Add PATCH method override
    const methodInput = document.createElement('input');
    methodInput.type = 'hidden';
    methodInput.name = '_method';
    methodInput.value = 'PATCH';
    form.appendChild(methodInput);
    
    document.getElementById('form-title').textContent = 'Edit Address';
    document.getElementById('submit-text').textContent = 'Update Address';

    // Show form
    document.getElementById('address-form-container').classList.remove('hidden');
    document.getElementById('toggle-form-btn').textContent = '− Cancel';
}

function resetForm() {
    document.getElementById('address-form').reset();
    document.getElementById('address-id').value = '';
    document.getElementById('address-form').action = '{{ route("profile.addresses.store") }}';
    document.getElementById('form-title').textContent = 'Add New Address';
    document.getElementById('submit-text').textContent = 'Add Address';
    
    // Remove any existing method override
    const methodInput = document.querySelector('input[name="_method"]');
    if (methodInput) {
        methodInput.remove();
    }
}
</script>
