@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Checkout Form -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Shipping Address -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>
                    
                    <form action="{{ route('checkout.store') }}" method="POST" id="checkout-form">
                        @csrf
                        @if($addresses->count() > 0)
                        <div class="space-y-3 mb-4" id="address-list">
                            @foreach($addresses as $address)
                            <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-amber-500 transition {{ $address->is_default ? 'border-amber-500' : 'border-gray-200' }}">
                                <input type="radio" name="address_id" value="{{ $address->id }}" {{ $address->is_default ? 'checked' : '' }} required class="mt-1 mr-3">
                                <div class="flex-1">
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
                            </label>
                            @endforeach
                        </div>
                        @else
                        <div id="address-list" class="mb-4">
                            <p class="text-gray-600 text-sm mb-4">No addresses found. Please add a shipping address.</p>
                        </div>
                        @endif
                    </form>

                    <button type="button" id="toggle-address-form" class="text-amber-600 hover:text-amber-700 text-sm font-medium">
                        + Add New Address
                    </button>

                    <!-- Add Address Form - Outside main form to prevent nesting -->
                    <div id="address-form" class="{{ $addresses->count() === 0 ? '' : 'hidden' }} mt-4 p-4 border-2 border-gray-200 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add New Address</h3>
                        <form id="new-address-form" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="label" class="block text-sm font-medium text-gray-700 mb-1">Label (e.g., Home, Work)</label>
                                    <input type="text" id="label" name="label" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <span class="text-red-500 text-xs error-message" id="error-name"></span>
                                </div>
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" id="phone" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>

                            <div>
                                <label for="address_line1" class="block text-sm font-medium text-gray-700 mb-1">Address Line 1 <span class="text-red-500">*</span></label>
                                <input type="text" id="address_line1" name="address_line1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <span class="text-red-500 text-xs error-message" id="error-address_line1"></span>
                            </div>

                            <div>
                                <label for="address_line2" class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                                <input type="text" id="address_line2" name="address_line2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                                    <input type="text" id="city" name="city" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <span class="text-red-500 text-xs error-message" id="error-city"></span>
                                </div>
                                <div>
                                    <label for="state" class="block text-sm font-medium text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
                                    <input type="text" id="state" name="state" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <span class="text-red-500 text-xs error-message" id="error-state"></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                                    <input type="text" id="country" name="country" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <span class="text-red-500 text-xs error-message" id="error-country"></span>
                                </div>
                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">Postal Code <span class="text-red-500">*</span></label>
                                    <input type="text" id="postal_code" name="postal_code" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <span class="text-red-500 text-xs error-message" id="error-postal_code"></span>
                                </div>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" id="is_default" name="is_default" value="1" class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                                <label for="is_default" class="ml-2 block text-sm text-gray-700">Set as default address</label>
                            </div>

                            <div id="address-form-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"></div>
                            <div id="address-form-success" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"></div>

                            <div class="flex gap-2">
                                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-md font-medium transition">
                                    Add Address
                                </button>
                                <button type="button" id="cancel-address-form" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md font-medium transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Payment Method</h2>
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-amber-500 transition">
                                <input type="radio" name="payment_method" value="cod" checked form="checkout-form" class="mr-3">
                                <div>
                                    <div class="font-semibold text-gray-900">Cash on Delivery</div>
                                    <div class="text-sm text-gray-600">Pay when you receive your order</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>
                    
                    <div class="space-y-3 mb-4">
                        @foreach($cart->items as $item)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ $item->variant->product->name }} x{{ $item->quantity }}</span>
                            <span class="text-gray-900">₹{{ number_format($item->quantity * $item->price, 2) }}</span>
                        </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-200 pt-4 space-y-2">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span>₹{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span>₹{{ number_format($shippingTotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Tax</span>
                            <span>₹{{ number_format($taxTotal, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 mt-2">
                            <div class="flex justify-between font-bold text-gray-900 text-lg">
                                <span>Total</span>
                                <span>₹{{ number_format($grandTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" form="checkout-form" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-3 rounded-lg font-semibold transition mt-6">
                        Place Order
                    </button>

                    <a href="{{ route('cart.index') }}" class="block text-center text-amber-600 hover:text-amber-700 mt-4 text-sm">
                        ← Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-address-form');
    const addressForm = document.getElementById('address-form');
    const cancelBtn = document.getElementById('cancel-address-form');
    const newAddressForm = document.getElementById('new-address-form');
    const addressList = document.getElementById('address-list');
    const errorsDiv = document.getElementById('address-form-errors');
    const successDiv = document.getElementById('address-form-success');

    // Toggle address form
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            addressForm.classList.toggle('hidden');
            if (!addressForm.classList.contains('hidden')) {
                // Clear form and messages when opening
                newAddressForm.reset();
                errorsDiv.classList.add('hidden');
                successDiv.classList.add('hidden');
                clearErrorMessages();
            }
        });
    }

    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            addressForm.classList.add('hidden');
            newAddressForm.reset();
            errorsDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            clearErrorMessages();
        });
    }

    // Handle form submission
    if (newAddressForm) {
        newAddressForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous messages
            errorsDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            clearErrorMessages();

            // Get form data
            const formData = new FormData(newAddressForm);
            const isDefault = document.getElementById('is_default').checked;
            formData.set('is_default', isDefault ? '1' : '0');

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Submit via AJAX
            fetch('{{ route("checkout.address.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(response => {
                // Parse response as JSON first
                return response.json().then(data => {
                    // Check if response is ok (200-299 status codes)
                    if (!response.ok) {
                        // Handle validation errors (422) or other errors
                        throw { response: { data: data } };
                    }
                    return data;
                }).catch(error => {
                    // If JSON parsing fails, it might be HTML (redirect response)
                    if (error.response) {
                        throw error;
                    }
                    throw { response: { data: { message: 'Invalid response from server' } } };
                });
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    successDiv.textContent = data.message || 'Address added successfully!';
                    successDiv.classList.remove('hidden');
                    
                    // Add new address to the list
                    addAddressToList(data.address);
                    
                    // Reset form
                    newAddressForm.reset();
                    
                    // Hide form after a short delay
                    setTimeout(() => {
                        addressForm.classList.add('hidden');
                        successDiv.classList.add('hidden');
                    }, 2000);
                } else {
                    // Show error message
                    if (data.message) {
                        errorsDiv.textContent = data.message;
                        errorsDiv.classList.remove('hidden');
                    } else {
                        errorsDiv.textContent = 'An error occurred. Please try again.';
                        errorsDiv.classList.remove('hidden');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (error.response && error.response.data) {
                    const data = error.response.data;
                    if (data.errors) {
                        displayValidationErrors(data.errors);
                    } else if (data.message) {
                        errorsDiv.textContent = data.message;
                        errorsDiv.classList.remove('hidden');
                    } else {
                        errorsDiv.textContent = 'An error occurred. Please try again.';
                        errorsDiv.classList.remove('hidden');
                    }
                } else {
                    errorsDiv.textContent = 'An error occurred. Please try again.';
                    errorsDiv.classList.remove('hidden');
                }
            });
        });
    }

    function addAddressToList(address) {
        // Remove "no addresses" message if present
        const noAddressMsg = addressList.querySelector('p');
        if (noAddressMsg) {
            noAddressMsg.remove();
        }

        // Create new address radio button
        const addressLabel = document.createElement('label');
        addressLabel.className = 'flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-amber-500 transition border-amber-500';
        
        const addressHtml = `
            <input type="radio" name="address_id" value="${address.id}" checked required form="checkout-form" class="mt-1 mr-3">
            <div class="flex-1">
                <div class="font-semibold text-gray-900">${address.name || ''}</div>
                <div class="text-sm text-gray-600 mt-1">
                    ${address.address_line1 || ''}<br>
                    ${address.address_line2 ? address.address_line2 + '<br>' : ''}
                    ${address.city || ''}, ${address.state || ''} ${address.postal_code || ''}<br>
                    ${address.country || ''}
                </div>
                ${address.phone ? '<div class="text-sm text-gray-600 mt-1">Phone: ' + address.phone + '</div>' : ''}
            </div>
        `;
        
        addressLabel.innerHTML = addressHtml;
        
        // Uncheck other addresses
        const existingRadios = addressList.querySelectorAll('input[type="radio"]');
        existingRadios.forEach(radio => {
            radio.checked = false;
            radio.closest('label').classList.remove('border-amber-500');
            radio.closest('label').classList.add('border-gray-200');
        });
        
        // Add new address to the list
        if (addressList.children.length > 0) {
            addressList.insertBefore(addressLabel, addressList.firstChild);
        } else {
            addressList.appendChild(addressLabel);
        }
    }

    function displayValidationErrors(errors) {
        // Clear previous error messages
        clearErrorMessages();
        
        // Display errors
        let errorHtml = '<ul class="list-disc list-inside">';
        Object.keys(errors).forEach(field => {
            errors[field].forEach(error => {
                errorHtml += `<li>${error}</li>`;
                
                // Show field-specific error
                const errorElement = document.getElementById(`error-${field}`);
                if (errorElement) {
                    errorElement.textContent = error;
                }
            });
        });
        errorHtml += '</ul>';
        
        errorsDiv.innerHTML = errorHtml;
        errorsDiv.classList.remove('hidden');
    }

    function clearErrorMessages() {
        // Clear all error message spans
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
        });
    }
});
</script>
@endsection
