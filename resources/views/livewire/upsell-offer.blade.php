<div>
    <!-- ✅ Loader em HTML puro (não depende de Livewire, aparece IMEDIATAMENTE) -->
    <div id="simple-payment-loader" style="display: none !important; visibility: hidden !important; pointer-events: none !important;">
        <div class="loader-box">
            <!-- Ícone de sucesso -->
            <svg class="loader-icon loader-icon-success" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" width="70" height="70">
                <circle cx="32" cy="32" r="30" fill="none" stroke="#00ff88" stroke-width="2" opacity="0.3"/>
                <circle cx="32" cy="32" r="30" fill="none" stroke="#00ff88" stroke-width="3" stroke-dasharray="188" stroke-dashoffset="188" style="animation: drawCircle 0.6s ease-out forwards;"/>
                <path d="M 24 34 L 30 40 L 42 28" fill="none" stroke="#00ff88" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="20" stroke-dashoffset="20" style="animation: drawCheck 0.5s ease-out 0.3s forwards;"/>
                <style>
                    @keyframes drawCircle {
                        to { stroke-dashoffset: 0; }
                    }
                    @keyframes drawCheck {
                        to { stroke-dashoffset: 0; }
                    }
                </style>
            </svg>
            
            <!-- Ícone de erro -->
            <svg class="loader-icon loader-icon-error" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" width="70" height="70">
                <circle cx="32" cy="32" r="30" fill="none" stroke="#ff4444" stroke-width="2" opacity="0.3"/>
                <circle cx="32" cy="32" r="30" fill="none" stroke="#ff4444" stroke-width="3" stroke-dasharray="188" stroke-dashoffset="188" style="animation: drawErrorCircle 0.6s ease-out forwards;"/>
                <path d="M 24 24 L 40 40" stroke="#ff4444" stroke-width="3" stroke-linecap="round" stroke-dasharray="23" stroke-dashoffset="23" style="animation: drawX 0.4s ease-out 0.2s forwards;"/>
                <path d="M 40 24 L 24 40" stroke="#ff4444" stroke-width="3" stroke-linecap="round" stroke-dasharray="23" stroke-dashoffset="23" style="animation: drawX 0.4s ease-out 0.4s forwards;"/>
                <style>
                    @keyframes drawErrorCircle {
                        to { stroke-dashoffset: 0; }
                    }
                    @keyframes drawX {
                        to { stroke-dashoffset: 0; }
                    }
                </style>
            </svg>
            
            <!-- Spinner de carregamento -->
            <div class="loader-spinner"></div>
            
            <div class="loader-text">{{ __('upsell.processando_pagamento') }}</div>
            <div class="loader-sub">{{ __('upsell.processando_segundos') }}</div>
        </div>
    </div>
    
    <!-- Header -->
    <div class="border-b border-red-900/30 px-6 py-4">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-red-600">SNAPHUBB</h1>
            <span class="text-sm text-gray-400">{{ app()->getLocale() === 'br' ? 'pt-Português' : (app()->getLocale() === 'en' ? 'en-English' : 'es-Español') }}</span>
        </div>
    </div>

    @if($pixQrImage)
    <!-- TELA 2: PÁGINA DE PIX -->
    <div class="max-w-5xl mx-auto px-6 py-12">
        <!-- Title -->
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold mb-2">{{ __('upsell.finalize_pagamento_pix') }}</h2>
            <p class="text-gray-400">{{ __('upsell.confirmacao_instantanea') }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
            <!-- Left: Resumo do Pedido -->
            <div>
                <h3 class="text-2xl font-bold mb-6">{{ __('upsell.resumo_do_pedido') }}</h3>
                
                <div class="space-y-6">
                    <!-- Produto Upsell -->
                    <div class="border border-red-600/40 bg-red-600/10 rounded-xl p-4">
                        <div class="flex gap-4">
                            <div class="w-20 h-20 bg-red-600/40 rounded-lg flex-shrink-0 flex items-center justify-center">
                                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-red-500 text-sm font-semibold"><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"></circle><path d="M10.39 12.65l2.45 2.45 5.92-5.92" stroke="white" stroke-width="2" fill="none"></path></svg> {{ __('upsell.oferta_exclusiva') }}</p>
                                <p class="font-bold">{{ $product['label'] }}</p>
                                <p class="text-gray-500 text-sm mt-2">R$ {{ number_format(($product['price'] ?? 3700)/100, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Discount -->
                    <div class="bg-green-600/10 border border-green-600/30 rounded-xl p-4">
                        <p class="text-green-500 font-semibold text-sm mb-2">🎉 {{ __('upsell.desconto_pix_aplicado') }}</p>
                        <p class="text-gray-400 text-sm">{{ __('upsell.voce_economiza') }} R$ {{ number_format((($product['origin_price'] ?? 97) - (($product['price'] ?? 3700)/100)), 2, ',', '.') }}</p>
                    </div>

                    <!-- Total -->
                    <div class="bg-gradient-to-r from-red-600/20 to-red-600/10 border border-red-600/40 rounded-xl p-6">
                        <p class="text-gray-400 text-sm mb-2">{{ __('upsell.total_a_pagar') }}</p>
                        <p class="text-4xl font-bold text-white">R$ {{ number_format(($product['price'] ?? 3700)/100, 2, ',', '.') }}</p>
                        <p class="text-gray-500 text-sm mt-3">{{ __('upsell.acesso_imediato_apos_confirmacao') }}</p>
                    </div>

                    <!-- Trust -->
                    <div class="flex items-center gap-2 text-blue-400 text-sm bg-blue-600/10 border border-blue-600/30 rounded-lg p-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <span>{{ __('upsell.dados_seguros_criptografados') }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: QR Code -->
            <div>
                <h3 class="text-2xl font-bold mb-6">{{ __('upsell.pagar_com_pix') }}</h3>

                <div class="space-y-6">
                    <!-- QR Code -->
                    <div class="bg-gradient-to-br from-gray-900 to-black border border-gray-800 rounded-2xl p-8">
                        <p class="text-center text-gray-400 text-sm mb-6">{{ __('upsell.escanear_celular') }}</p>
                        
                        <div class="bg-white p-4 rounded-xl mb-4 flex justify-center">
                            @php
                                // $pixQrImage já vem com prefixo data:image/png;base64 da API
                                $imgSrc = strpos($pixQrImage, 'data:') === 0 ? $pixQrImage : 'data:image/png;base64,' . $pixQrImage;
                            @endphp
                            <img src="{{ $imgSrc }}" alt="QR PIX" class="w-48 h-48 object-contain">
                        </div>

                        <p class="text-center text-gray-500 text-sm">{{ __('upsell.abra_app_bancario') }}</p>
                    </div>

                    <!-- Divider -->
                    <div class="flex items-center gap-4">
                        <div class="flex-1 border-t border-gray-800"></div>
                        <p class="text-gray-500 text-sm">OU</p>
                        <div class="flex-1 border-t border-gray-800"></div>
                    </div>

                    <!-- Copia e Cola -->
                    <div>
                        <p class="text-gray-400 text-sm mb-3">{{ __('upsell.copie_codigo_pix') }}</p>
                        
                        <div id="pix-code-container" class="bg-gray-900 border border-gray-800 rounded-lg p-4 mb-4 font-mono text-gray-400 text-xs break-all select-all overflow-x-auto max-h-24">
                            {{ $pixQrCodeText }}
                        </div>

                        <button id="copy-pix-btn" onclick="copyPixCode()" class="w-full py-4 px-6 rounded-lg font-bold transition-all duration-300 flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            <span id="copy-text">{{ __('upsell.ativar_painel') === 'Copiar Código PIX' ? __('upsell.ativar_painel') : __('upsell.ativar_painel') }}</span>
                        </button>

                        <p class="text-center text-gray-500 text-xs mt-3">{{ __('upsell.cole_app_bancario') }}</p>
                    </div>

                    <!-- Status -->
                    <div class="bg-yellow-600/10 border border-yellow-600/30 rounded-lg p-4 mb-6">
                        <p class="text-yellow-500 font-semibold text-sm mb-1"><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> {{ __('upsell.aguardando_pagamento') }}</p>
                        <p class="text-gray-400 text-sm">{{ __('upsell.sera_redirecionado_automaticamente') }}</p>
                        <p class="text-gray-500 text-xs mt-2">{{ __('upsell.status') }}: <strong id="pix-status">{{ $pixStatus }}</strong></p>
                    </div>

                    <!-- Botão: Continuar apenas com streaming -->
                    <button onclick="window.location.href='/upsell/thank-you-recused-qr'" class="w-full bg-gray-900 hover:bg-gray-800 text-gray-300 font-semibold py-4 rounded-lg transition-all duration-300 border border-gray-700 cursor-pointer">
                        {{ __('upsell.continuar_apenas_streaming') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-500 text-xs">
            <p><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> {{ __('upsell.voce_esta_seguro') }} • {{ __('upsell.privacy_policy') }} • {{ __('upsell.terms_of_service') }}</p>
        </div>
    </div>
@else
    <!-- TELA 1: PÁGINA DE UPSELL -->
    <div class="max-w-5xl mx-auto px-6 py-12">
        <!-- Email Confirmation Header -->
        <div class="text-center mb-12">
            <div class="inline-block bg-red-600/10 border border-red-600/30 rounded-full px-6 py-2 mb-4">
                <p class="text-red-500 text-sm font-semibold"><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> {{ __('upsell.email_confirmation_sent') }}</p>
            </div>
            <h2 class="text-4xl font-bold mb-4">{{ __('upsell.parabens_compra_confirmada') }}</h2>
            <p class="text-gray-400 text-lg mb-2">{{ __('upsell.streaming_pago') }}</p>
            <p class="text-green-500 font-semibold text-xl">R$ 24,90 {{ __('upsell.pago_com_sucesso') }}</p>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-800 my-12"></div>

        <!-- Upsell Offer Section -->
        <div class="mb-12">
            <h3 class="text-center text-3xl font-bold mb-12">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 inline" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" fill="none"></path></svg> {{ __('upsell.wait_exclusive_offer') }}
            </h3>

            <!-- Offer Card -->
            <div class="bg-gradient-to-br from-red-600/20 via-black to-black border border-red-600/40 rounded-2xl p-8 md:p-12 mb-8">
                <div class="mb-8">
                    <!-- Headline -->
                    <h4 class="text-2xl md:text-3xl font-bold mb-6 leading-tight">{{ __('upsell.discover_girls') }}</h4>
                    
                    <!-- Subtítulo -->
                    <div class="mb-6">
                        <p class="text-red-500 font-semibold text-lg mb-2">{{ __('upsell.add_elite_filter') }}</p>
                        <p class="text-gray-300">{{ __('upsell.acesso_imediato_playlist') }}</p>
                    </div>

                    <!-- Video Section - Mobile Maior -->
                    <div class="mb-8 rounded-xl overflow-hidden relative mx-auto" style="box-shadow: 0 0 30px rgba(239, 68, 68, 0.3), 0 0 60px rgba(239, 68, 68, 0.1); border: 2px solid rgba(239, 68, 68, 0.4); max-width: 100%;">
                        <div id="vid_684e79adc8f6673056a0efcb" style="position: relative; width: 100%; padding: 56.66316894018888% 0 0;">
                            <img id="thumb_684e79adc8f6673056a0efcb" src="https://images.converteai.net/3ae6d848-4622-4faa-b895-f9530f8170cd/players/684e79adc8f6673056a0efcb/thumbnail.jpg" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; display: block;" alt="thumbnail">
                            <div id="backdrop_684e79adc8f6673056a0efcb" style="-webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px); position: absolute; top: 0; height: 100%; width: 100%;"></div>
                        </div>
                    </div>
                    <script type="text/javascript" id="scr_684e79adc8f6673056a0efcb">
                        var s=document.createElement("script");
                        s.src="https://scripts.converteai.net/3ae6d848-4622-4faa-b895-f9530f8170cd/players/684e79adc8f6673056a0efcb/player.js",
                        s.async=!0,
                        document.head.appendChild(s);
                    </script>

                    <!-- CTA Buttons - Below Video -->
                    <div class="space-y-3 mb-8">
                        <button id="upsell-checkout-button" wire:click="aproveOffer" @onclick="window.showPaymentLoader && window.showPaymentLoader()" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-lg transition-all duration-300 flex items-center justify-center gap-2 text-lg group cursor-pointer">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            {{ __('upsell.ativar_painel') }}
                        </button>
                        <button wire:click="declineOffer" class="w-full bg-gray-900 hover:bg-gray-800 text-gray-300 font-semibold py-4 rounded-lg transition-all duration-300 border border-gray-700 cursor-pointer">
                            {{ __('upsell.continuar_apenas_streaming') }}
                        </button>
                    </div>

                    <!-- Corpo do Texto -->
                    <div class="mb-6 space-y-4">
                        <p class="text-gray-300">{{ __('upsell.ja_garantiu') ?? 'Você já garantiu seu acesso, mas deixe-me te fazer uma pergunta rápida:' }}</p>
                        <p class="text-white font-semibold text-lg">{{ __('upsell.por_que_perder_horas') }}</p>
                        <p class="text-gray-300">{{ __('upsell.painel_garotas_remove_duvida') }}<br>{{ __('upsell.nao_recebe_mais_conteudo') }}<br><strong class="text-white">{{ __('upsell.recebe_nata_nata') }}</strong>, {{ __('upsell.filtrada_comunidade') }}</p>
                    </div>

                    <!-- Como Funciona -->
                    <div class="mb-8">
                        <h5 class="text-xl font-bold text-white mb-4">{{ __('upsell.como_funciona_elite') }}</h5>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0">🗳️</span>
                                <div>
                                    <p class="text-white font-semibold">{{ __('upsell.votacao_ativa') }}</p>
                                    <p class="text-gray-300 text-sm">{{ __('upsell.votacao_ativa_desc') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0">🏆</span>
                                <div>
                                    <p class="text-white font-semibold">{{ __('upsell.a_selecao') }}</p>
                                    <p class="text-gray-300 text-sm">{{ __('upsell.vencedora_entra_playlist') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0">🔥</span>
                                <div>
                                    <p class="text-white font-semibold">{{ __('upsell.o_acesso') }}</p>
                                    <p class="text-gray-300 text-sm">{{ __('upsell.catalogo_completo_vencedora') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Benefício Principal -->
                    <div class="bg-gradient-to-r from-yellow-600/20 to-yellow-500/10 border border-yellow-500/40 rounded-xl p-6 mb-8">
                        <h5 class="text-xl font-bold text-yellow-400 mb-3">{{ __('upsell.grande_segredo') }}</h5>
                        <p class="text-white font-bold text-lg mb-3">{{ __('upsell.garantia_blindada_acesso_vitalicio') }}</p>
                        <p class="text-gray-300 text-sm mb-4">{{ __('upsell.maioria_assinaturas_corta') }}</p>
                        <p class="text-gray-300 text-sm mb-4">{{ __('upsell.ao_adicionar_hoje') }}</p>
                        <p class="text-white font-semibold">{{ __('upsell.pode_cancelar_assinatura') }}</p>
                        <p class="text-gray-400 text-xs mt-4 italic">{{ __('upsell.unica_assinatura_recompensa') }}</p>
                    </div>

                    <!-- Pricing Section -->
                    <div class="bg-black/50 border border-gray-700 rounded-xl p-6 mb-8">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <p class="text-gray-500 text-sm mb-1">{{ __('upsell.preco_original') }}</p>
                                <p class="text-xl line-through text-gray-500">R$ {{ number_format($product['origin_price'] ?? 97, 2, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm mb-1">{{ __('upsell.desconto_pix') }}</p>
                                <p class="text-xl font-bold text-red-500">-R$ {{ number_format((($product['origin_price'] ?? 97) - (($product['price'] ?? 3700)/100)), 2, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="border-t border-gray-700 pt-6">
                            <p class="text-gray-400 mb-2">{{ __('upsell.voce_vai_pagar') }}</p>
                            <div class="flex items-baseline gap-2">
                                <p class="text-5xl font-bold text-white">R$ {{ number_format(($product['price'] ?? 3700)/100, 2, ',', '.') }}</p>
                            </div>
                            <p class="text-green-500 font-semibold mt-4"><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" fill="none"></path></svg> {{ __('upsell.voce_economiza') }} R$ {{ number_format((($product['origin_price'] ?? 97) - (($product['price'] ?? 3700)/100)), 2, ',', '.') }}</p>
                        </div>
                    </div>

                    <!-- Urgency + Social Proof -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <!-- Urgência -->
                        <div class="bg-red-600/10 border border-red-600/30 rounded-lg p-4 flex items-center gap-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <div>
                                <p class="text-red-500 font-semibold text-sm">⏰ {{ __('upsell.oferta_limitada') }}</p>
                                <p class="text-gray-300 text-xs mt-1"><strong class="text-red-400">{{ __('upsell.apenas_quem_compra_agora') }}</strong><br>{{ __('upsell.bonus_valido') }}</p>
                            </div>
                        </div>

                        <!-- Prova Social - Avaliações -->
                        <div class="bg-yellow-600/10 border border-yellow-600/30 rounded-lg p-4 flex items-center gap-3">
                            <div class="flex flex-col items-center gap-1">
                                <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                <span class="text-yellow-400 font-bold text-sm">4.9/5</span>
                            </div>
                            <div>
                                <p class="text-yellow-400 font-semibold text-sm">{{ __('upsell.melhor_avaliado') }}</p>
                                <p class="text-gray-300 text-xs mt-1">
                                    <svg class="w-3 h-3 inline text-green-500 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                    1.200+ {{ __('upsell.pessoas_adicionaram') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="space-y-3">
                        <button id="upsell-checkout-button" wire:click="aproveOffer" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-lg transition-all duration-300 flex items-center justify-center gap-2 text-lg group cursor-pointer">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            {{ __('upsell.ativar_painel') }}
                        </button>
                        <button wire:click="declineOffer" class="w-full bg-gray-900 hover:bg-gray-800 text-gray-300 font-semibold py-4 rounded-lg transition-all duration-300 border border-gray-700 cursor-pointer">
                            {{ __('upsell.continuar_apenas_streaming') }}
                        </button>
                    </div>

                    @if($errorMessage)
                        <div class="mt-4 bg-red-600/10 border border-red-600/30 rounded-lg p-4">
                            <p class="text-red-400 text-sm">{{ $errorMessage }}</p>
                        </div>
                    @endif

                    <!-- Trust Indicators -->
                    <div class="mt-8 pt-8 border-t border-gray-800 space-y-2 text-sm text-gray-400">
                        <p class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            {{ __('upsell.pagamento_seguro_pix') }}
                        </p>
                        <p><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> {{ __('upsell.sem_compromisso') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Proof -->
        <div class="text-center text-gray-500 text-sm">
            <p><svg class="w-4 h-4 sm:w-5 sm:h-5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> 2.847 {{ __('upsell.pessoas_aprovaram_oferta') }}</p>
            <p>⭐ 4.8/5 {{ __('upsell.satisfacao') }}</p>
        </div>
    </div>
    @endif

    <script>
        // ✅ Loader control functions - MUST RUN BEFORE Livewire listeners
        (function(){
            var loader = document.getElementById('simple-payment-loader');
            var title = loader ? loader.querySelector('.loader-text') : null;
            var sub = loader ? loader.querySelector('.loader-sub') : null;
            var lang = document.documentElement.lang || 'br';
            
            var strings = {
                processing: {br: '{{ __("upsell.processando_pagamento") }}', en: '{{ __("upsell.processando_pagamento") }}', es: '{{ __("upsell.processando_pagamento") }}'},
                success: {br: '{{ __("upsell.parabens_compra_aprovada") }}', en: '{{ __("upsell.parabens_compra_aprovada") }}', es: '{{ __("upsell.parabens_compra_aprovada") }}'},
                failed: {br: '{{ __("upsell.pagamento_nao_aprovado") }}', en: '{{ __("upsell.pagamento_nao_aprovado") }}', es: '{{ __("upsell.pagamento_nao_aprovado") }}'}
            };
            
            window.showPaymentLoader = function() {
                if (!loader) {
                    console.error('❌ Loader element not found!');
                    return;
                }
                if (title) title.textContent = strings['processing'] ? (strings['processing'][lang] || strings['processing']['br']) : 'Processando...';
                if (sub) sub.style.display = 'block';
                loader.classList.add('show', 'loading');
                loader.classList.remove('success', 'failed');
                document.body.classList.add('overflow-hidden');
                console.log('✅ Upsell Loader shown with state: processing');
            };
        })();

        function copyPixCode(){
            const code = document.getElementById('pix-code-container').textContent.trim();
            navigator.clipboard.writeText(code).then(() => {
                const btn = document.getElementById('copy-pix-btn');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Código Copiado!</span>';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600');
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('bg-green-600');
                    btn.classList.add('bg-red-600', 'hover:bg-red-700');
                }, 2000);
            });
        }

        window.addEventListener('upsell:pix-generated', function(e){
            // start polling every 8s
            const paymentId = e.detail.payment_id;
            if(!paymentId) return;
            const interval = setInterval(function(){
                fetch('/api/pix/status/' + paymentId)
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success'){
                            const st = d.data.payment_status || d.data.status || '';
                            document.getElementById('pix-status').textContent = st;
                            if(st === 'approved' || st === 'paid'){
                                clearInterval(interval);
                                // redirect to thank you for upsell purchase
                                window.location.href = '/upsell/thank-you';
                            }
                            if(st === 'cancelled' || st === 'rejected' || st === 'expired'){
                                clearInterval(interval);
                            }
                        }
                    }).catch(()=>{});
            }, 8000);
        });

        // Listen for upsell success event from backend
        if (window.Livewire) {
            window.Livewire.on('upsell-success', function(payload) {
                console.log('✅ upsell-success event received', payload);
                const loader = document.getElementById('simple-payment-loader');
                
                if (loader) {
                    // Show loader with success state
                    loader.style.display = 'block';
                    loader.style.visibility = 'visible';
                    loader.style.pointerEvents = 'auto';
                    loader.classList.remove('loading', 'failed');
                    loader.classList.add('show', 'success');
                    
                    const titleEl = loader.querySelector('.loader-text') || document.querySelector('.loader-text');
                    if (titleEl) titleEl.textContent = 'Parabéns! Compra aprovada.';
                    
                    setTimeout(function() {
                        if (payload && payload.redirect_url) {
                            window.location.href = payload.redirect_url;
                        }
                    }, 2000);
                } else if (payload && payload.redirect_url) {
                    setTimeout(function() {
                        window.location.href = payload.redirect_url;
                    }, 1000);
                }
            });

            // Listen for upsell failed event from backend
            window.Livewire.on('upsell-failed', function(payload) {
                console.log('❌ upsell-failed event received', payload);
                const loader = document.getElementById('simple-payment-loader');
                
                if (loader) {
                    // Show loader with failed state
                    loader.style.display = 'block';
                    loader.style.visibility = 'visible';
                    loader.style.pointerEvents = 'auto';
                    loader.classList.remove('loading', 'success');
                    loader.classList.add('show', 'failed');
                    
                    const titleEl = loader.querySelector('.loader-text') || document.querySelector('.loader-text');
                    if (titleEl) titleEl.textContent = 'Pagamento não aprovado. Verifique os dados e tente novamente.';
                    window.update('failed');
                    setTimeout(function() {
                        if (payload && payload.redirect_url) {
                            window.location.href = payload.redirect_url;
                        }
                    }, 2000);
                } else if (payload && payload.redirect_url) {
                    setTimeout(function() {
                        window.location.href = payload.redirect_url;
                    }, 1000);
                }
            });
        }
    </script>
</div>