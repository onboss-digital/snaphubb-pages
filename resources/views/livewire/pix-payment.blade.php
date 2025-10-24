<div>
<!-- New Unified PIX Modal -->
<div id="pix-modal"
    x-data="{ show: @entangle('showPixModal') }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
    @keydown.escape.window="show = false; $wire.call('closeModal')"
    wire:poll.3000ms="checkPixPaymentStatus"
    style="display: none;"
    >
    <div class="fixed inset-0" @click="show = false; $wire.call('closeModal')"></div>

    <div class="bg-[#1F1F1F] rounded-xl max-w-3xl w-full mx-4 p-8 z-10 relative" @click.stop>
        <!-- Step 1: PIX Form -->
        <div x-show="!$wire.pixQrCodeBase64 && !$wire.showLodingModal" x-transition>
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Left Side: Form -->
                <div class="w-full md:w-1/2 order-2 md:order-1">
                    <h3 class="text-2xl font-bold text-white mb-2">{{ __('payment.pix_title') }}</h3>
                    <p class="text-gray-300 mb-6">{{ __('payment.pix_subtitle') }}</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.full_name') }}</label>
                            <input name="pix_name" type="text" placeholder="{{ __('payment.full_name') }}" wire:model.defer="pix_name" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border @error('pix_name') border-red-500 @else border-gray-700 @enderror focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                            @error('pix_name')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.email') }}</label>
                            <input name="pix_email" type="email" placeholder="{{ __('payment.email_placeholder') }}" wire:model.defer="pix_email" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border @error('pix_email') border-red-500 @else border-gray-700 @enderror focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                            @error('pix_email')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.cpf') }}</label>
                            <input name="pix_cpf" type="text" placeholder="{{ __('payment.cpf_placeholder') }}" wire:model.defer="pix_cpf" x-mask="999.999.999-99" class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border @error('pix_cpf') border-red-500 @else border-gray-700 @enderror focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                            @error('pix_cpf')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="pix_phone_field" class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.phone') }}</label>
                            <input id="pix_phone_field" name="pix_phone" type="tel" placeholder="{{ __('payment.phone_placeholder') }}" wire:model.defer="pix_phone" class="pix-phone-field w-full bg-[#2D2D2D] text-white rounded-lg p-3 border @error('pix_phone') border-red-500 @else border-gray-700 @enderror focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                            @error('pix_phone')<span class="text-red-500 text-xs mt-1">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <!-- Right Side: Order Summary -->
                <div class="w-full md:w-1/2 bg-[#2D2D2D] p-6 rounded-lg flex flex-col justify-between order-1 md:order-2">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">{{ __('payment.final_summary') }}</h3>
                        <img src="https://web.snaphubb.online/wp-content/uploads/2025/10/capa-brasil.jpg" alt="{{ __('payment.product_image_alt') }}" class="w-full h-auto max-h-64 object-cover rounded-lg border-2 border-gray-700 mb-4">
                        <div class="border-t border-gray-600 pt-4 space-y-3">
                            <div class="flex justify-between items-center text-gray-300">
                                <span class="text-lg">{{ __('payment.original_price') }}</span>
                                <del>{{ $totals['total_price'] ?? '0,00' }}</del>
                            </div>
                             <div class="flex justify-between items-center text-white">
                                <span class="text-lg font-bold">{{ __('payment.total_to_pay') }}</span>
                                <p class="font-bold text-green-400 text-2xl">{{ $totals['final_price'] ?? '0,00' }}</p>
                            </div>
                            <div class="text-green-400 font-semibold text-center bg-green-900 bg-opacity-50 rounded-md py-2">
                                {{ __('payment.pix_discount_applied') }}
                            </div>
                        </div>
                    </div>
                    <div class="text-center text-xs text-gray-400 mt-4">
                        <p>🔒 {{__('payment.your_data_is_safe')}}</p>
                    </div>
                </div>
            </div>
            <div class="mt-8 flex justify-between items-center">
                <button @click="show = false; $wire.call('closeModal')" class="py-2 px-6 text-white font-medium rounded-lg border border-gray-600 hover:bg-[#2D2D2D] transition-colors">
                    {{ __('payment.pix_cancel_button') }}
                </button>
                <button wire:click="startPixCheckout" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="py-3 px-8 bg-[#E50914] hover:bg-[#B8070F] text-white font-bold rounded-lg transition-colors text-lg flex items-center">
                    <span wire:loading.remove wire:target="startPixCheckout">{{ __('payment.generate_pix_button') }}</span>
                    <span wire:loading wire:target="startPixCheckout">{{ __('payment.generating_pix_button') }}
                        <svg class="animate-spin h-5 w-5 text-white ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </span>
                </button>
            </div>
        </div>

        <!-- Step 2: QR Code -->
        <div x-show="$wire.pixQrCodeBase64 && !$wire.showLodingModal" x-transition class="text-center">
            <h3 class="text-xl md:text-2xl font-bold text-white mb-2">{{ __('payment.pix_generated_title') }}</h3>
            <p class="text-gray-300 mb-4 text-sm md:text-base">{{ __('payment.pix_instructions') }}</p>

            <div class="flex justify-center mb-4">
                <img :src="'data:image/png;base64,' + $wire.pixQrCodeBase64" alt="{{ __('payment.pix_qr_code_alt') }}" class="rounded-lg border-4 border-white">
            </div>

            <div class="mb-4 w-full max-w-sm mx-auto">
                <input type="text" :value="$wire.pixQrCode" readonly
                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 text-center text-xs md:text-sm truncate">
                <button @click="navigator.clipboard.writeText($wire.pixQrCode); $event.target.innerText = '{{ __('payment.copied_button') }}'"
                    class="mt-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition-all duration-300 w-full md:w-auto">
                    {{ __('payment.copy_code_button') }}
                </button>
            </div>

            <div class="flex items-center justify-center text-yellow-400">
                 <svg class="animate-spin h-5 w-5 text-white mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V8H4z"></path>
                </svg>
                <span>{{ __('payment.waiting_for_payment') }}</span>
            </div>

            <button @click="show = false; $wire.call('closeModal')" class="mt-4 text-gray-400 hover:text-white">{{ __('payment.pix_pay_with_card_button') }}</button>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="fixed inset-0 bg-black bg-opacity-80 flex flex-col justify-center items-center text-white z-50 @if (!$showLodingModal) hidden @endif">
    <svg class="animate-spin h-10 w-10 text-red-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none"
        viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
            stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
    </svg>
    <p class="text-lg">{{ $loadingMessage }}</p>
</div>

<!-- Error Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showErrorModal) hidden @endif">
    <div class="bg-[#1F1F1F] rounded-xl p-8 max-w-md w-full mx-4 text-center">
        <h3 class="text-xl font-bold text-white mb-2">{{ __('payment.processing_error') }}</h3>
        <p class="text-gray-300">{{ $errorMessage }}</p>
            <button wire:click.prevent="closeModal"
                class="mt-4 bg-[#E50914] hover:bg-[#B8070F] text-white py-2 px-6 text-base font-semibold rounded-full shadow-lg w-auto min-w-[180px] max-w-xs mx-auto transition-all flex items-center justify-center cursor-pointer">
                {{ __('payment.close') }}
            </button>
    </div>
</div>

<!-- Success Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showSuccessModal) hidden @endif">
    <div class="bg-[#1F1F1F] rounded-xl p-8 max-w-md w-full mx-4 text-center">
        <h3 class="text-xl font-bold text-white mb-2">{{ __('payment.success') }}</h3>
            <button wire:click.prevent="closeModal"
                class="bg-[#42b70a] hover:bg-[#2a7904] text-white py-2 px-6 text-base font-semibold rounded-full shadow-lg w-auto min-w-[180px] max-w-xs mx-auto transition-all flex items-center justify-center cursor-pointer">
                {{ __('payment.close') }}
            </button>
    </div>
</div>
</div>
