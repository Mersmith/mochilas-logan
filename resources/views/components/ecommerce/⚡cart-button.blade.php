<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $cartCount = 0;

    public function mount(): void
    {
        $this->updateCount();
    }

    #[On('cart-updated')]
    public function updateCount(): void
    {
        $this->cartCount = \App\Services\CartService::count();
    }
};
?>

<a href="{{ route('carrito') }}" wire:navigate class="relative inline-block" title="{{ __('Bolsa de compras') }}">
    <flux:button variant="ghost" icon="shopping-bag" class="relative size-9" :as="'span'">
        @if($cartCount > 0)
            <span class="absolute -top-1.5 -right-1.5 bg-rose-600 text-white rounded-full text-[9px] font-extrabold h-4.5 w-4.5 flex items-center justify-center shadow-sm animate-in zoom-in-75 duration-200">
                {{ $cartCount }}
            </span>
        @endif
    </flux:button>
</a>
