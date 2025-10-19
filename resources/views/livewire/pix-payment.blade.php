<!-- New Unified PIX Modal -->
<div id="pix-modal-unified" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50" x-data="{
    show: @entangle('showPixModal'),
    step: @entangle('pixModalStep'),
    qrCode: @entangle('pixQrCode'),
    copyPaste: @entangle('pixCopyPaste'),
    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('{{ __('payment.pix_copied') }}');
        });
    }
}" x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="display: none;">

    <div class="bg-[#1F1F1F] rounded-2xl max-w-lg w-full mx-4 shadow-xl transform transition-all" @click.away="show = false">

        <!-- Step 1: PIX Form -->
        <div x-show="step === 'form'" class="p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-white">{{ __('payment.complete_to_generate_pix') }}</h3>
                <button @click="show = false" class="text-gray-400 hover:text-white">&times;</button>
            </div>

            <!-- Product Info -->
            <div class="bg-gray-800 rounded-lg p-4 flex items-center mb-6">
                <img src="{{ asset('imgs/mini_logo.png') }}" alt="Product" class="w-16 h-16 rounded-md mr-4">
                <div>
                    <h4 class="font-semibold text-white">SNAPHUBB PREMIUM</h4>
                    <p class="text-green-400 text-xl font-bold">R$ 24,90</p>
                    <p class="text-sm text-gray-400">{{ __('payment.pix_access_for_life') }}</p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_name') }}</label>
                    <input type="text" placeholder="{{ __('payment.card_name') }}" wire:model.defer="cardName" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914]">
                    @error('cardName')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">E-mail</label>
                    <input type="email" placeholder="seu@email.com" wire:model.live.debounce.500ms="email" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914]">
                    @error('email')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.phone') }}</label>
                    <input name="phone" id="phone" type="tel" placeholder="" wire:model="phone" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                    @error('phone')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">CPF</label>
                    <input type="text" x-mask="999.999.999-99" placeholder="000.000.000-00" wire:model.defer="cpf" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914]">
                    @error('cpf')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                </div>
            </div>

            <button wire:click.prevent="startPixCheckout" class="w-full mt-6 bg-[#00bdae] hover:bg-[#00a396] text-white py-3 text-lg font-bold rounded-xl transition-all">
                {{ __('payment.generate_pix') }}
            </button>
        </div>

        <!-- Step 2: QR Code Display -->
        <div x-show="step === 'qr_code'" class="p-8 text-center" style="display: none;">
            <h3 class="text-2xl font-bold text-white mb-2">{{ __('payment.pix_almost_done') }}</h3>
            <p class="text-gray-300 mb-4">{{ __('payment.pix_scan_qr') }}</p>

            <div class="flex justify-center mb-4">
                <img :src="'data:image/png;base64,' + qrCode" alt="PIX QR Code" class="rounded-lg border-4 border-white">
            </div>

            <p class="text-gray-400 mb-2">{{ __('payment.pix_or_copy') }}</p>

            <div class="relative bg-[#2D2D2D] rounded-lg p-3">
                <input type="text" :value="copyPaste" readonly class="w-full bg-transparent text-white text-sm border-none focus:ring-0 pr-10">
                <button @click="copyToClipboard(copyPaste)" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7 3a1 1 0 011-1h5a1 1 0 011 1v1h1a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h1V3zM4 6h12v10H4V6zm5-2h2v1H9V4z"></path>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-yellow-400 mt-4 animate-pulse">{{ __('payment.pix_waiting_payment') }}</p>

            <button @click="step = 'form'" class="mt-4 text-sm text-gray-400 hover:text-white">{{ __('payment.pix_wrong_data') }}</button>
        </div>
    </div>
</div>
