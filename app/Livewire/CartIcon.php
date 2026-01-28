<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;

class CartIcon extends Component
{
    public $count = 0;

    public function mount()
    {
        $this->updateCount();
    }

    public function updateCount()
    {
        if (Auth::check()) {
            $cart = Cart::where('user_id', Auth::id())->first();
            $this->count = $cart ? $cart->items()->sum('quantity') : 0;
        } else {
            $cartId = session('cart_id');
            if ($cartId) {
                $cart = Cart::find($cartId);
                $this->count = $cart ? $cart->items()->sum('quantity') : 0;
            }
        }
    }

    public function render()
    {
        return view('livewire.cart-icon');
    }
}
