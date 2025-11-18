<div>
    <!-- Header -->
    <div class="border-b border-red-900/30 px-6 py-4">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-red-600">SNAPHUBB</h1>
            <span class="text-sm text-gray-400">pt-Português</span>
        </div>
    </div>

    @if($pixQrImage)
    <!-- TELA 2: PÁGINA DE PIX -->
    <div class="max-w-5xl mx-auto px-6 py-12">
        <!-- Title -->
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold mb-2">Finalize seu Pagamento com PIX</h2>
            <p class="text-gray-400">Confirmação instantânea • Sem taxas adicionais</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
            <!-- Left: Resumo do Pedido -->
            <div>
                <h3 class="text-2xl font-bold mb-6">Resumo do Pedido</h3>
                
                <div class="space-y-6">
                    <!-- Produto Upsell -->
                    <div class="border border-red-600/40 bg-red-600/10 rounded-xl p-4">
                        <div class="flex gap-4">
                            <div class="w-20 h-20 bg-red-600/40 rounded-lg flex-shrink-0 flex items-center justify-center">
                                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-red-500 text-sm font-semibold">✨ Oferta Exclusiva</p>
                                <p class="font-bold">{{ $product['label'] }}</p>
                                <p class="text-gray-500 text-sm mt-2">R$ {{ number_format(($product['price'] ?? 3700)/100, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Discount -->
                    <div class="bg-green-600/10 border border-green-600/30 rounded-xl p-4">
                        <p class="text-green-500 font-semibold text-sm mb-2">🎉 Desconto PIX aplicado!</p>
                        <p class="text-gray-400 text-sm">Você economiza R$ {{ number_format((($product['origin_price'] ?? 97) - (($product['price'] ?? 3700)/100)), 2, ',', '.') }}</p>
                    </div>

                    <!-- Total -->
                    <div class="bg-gradient-to-r from-red-600/20 to-red-600/10 border border-red-600/40 rounded-xl p-6">
                        <p class="text-gray-400 text-sm mb-2">Total a Pagar</p>
                        <p class="text-4xl font-bold text-white">R$ {{ number_format(($product['price'] ?? 3700)/100, 2, ',', '.') }}</p>
                        <p class="text-gray-500 text-sm mt-3">Acesso imediato após confirmação</p>
                    </div>

                    <!-- Trust -->
                    <div class="flex items-center gap-2 text-blue-400 text-sm bg-blue-600/10 border border-blue-600/30 rounded-lg p-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <span>Seus dados estão 100% seguros e criptografados</span>
                    </div>
                </div>
            </div>

            <!-- Right: QR Code -->
            <div>
                <h3 class="text-2xl font-bold mb-6">Pagar com PIX</h3>

                <div class="space-y-6">
                    <!-- QR Code -->
                    <div class="bg-gradient-to-br from-gray-900 to-black border border-gray-800 rounded-2xl p-8">
                        <p class="text-center text-gray-400 text-sm mb-6">Escaneie com seu celular</p>
                        
                        <div class="bg-white p-4 rounded-xl mb-4 flex justify-center">
                            <img src="data:image/png;base64,{{ $pixQrImage }}" alt="QR PIX" class="w-48 h-48 object-contain">
                        </div>

                        <p class="text-center text-gray-500 text-sm">Abra seu app bancário e escaneie</p>
                    </div>

                    <!-- Divider -->
                    <div class="flex items-center gap-4">
                        <div class="flex-1 border-t border-gray-800"></div>
                        <p class="text-gray-500 text-sm">OU</p>
                        <div class="flex-1 border-t border-gray-800"></div>
                    </div>

                    <!-- Copia e Cola -->
                    <div>
                        <p class="text-gray-400 text-sm mb-3">Copie o código PIX</p>
                        
                        <div id="pix-code-container" class="bg-gray-900 border border-gray-800 rounded-lg p-4 mb-4 font-mono text-gray-400 text-xs break-all select-all overflow-x-auto max-h-24">
                            {{ $pixQrCodeText }}
                        </div>

                        <button id="copy-pix-btn" onclick="copyPixCode()" class="w-full py-4 px-6 rounded-lg font-bold transition-all duration-300 flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            <span id="copy-text">Copiar Código PIX</span>
                        </button>

                        <p class="text-center text-gray-500 text-xs mt-3">Cole no seu app bancário para pagar</p>
                    </div>

                    <!-- Status -->
                    <div class="bg-yellow-600/10 border border-yellow-600/30 rounded-lg p-4">
                        <p class="text-yellow-500 font-semibold text-sm mb-1">⏳ Aguardando Pagamento</p>
                        <p class="text-gray-400 text-sm">Você será redirecionado automaticamente ao confirmar</p>
                        <p class="text-gray-500 text-xs mt-2">Status: <strong id="pix-status">{{ $pixStatus }}</strong></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-500 text-xs">
            <p>🔒 Você está seguro com Snaphubb • Política de Privacidade • Termos de Serviço</p>
        </div>
    </div>
@else
    <!-- TELA 1: PÁGINA DE UPSELL -->
    <div class="max-w-5xl mx-auto px-6 py-12">
        <!-- Email Confirmation Header -->
        <div class="text-center mb-12">
            <div class="inline-block bg-red-600/10 border border-red-600/30 rounded-full px-6 py-2 mb-4">
                <p class="text-red-500 text-sm font-semibold">✓ Email de Confirmação Enviado</p>
            </div>
            <h2 class="text-4xl font-bold mb-4">Parabéns! Sua compra foi confirmada</h2>
            <p class="text-gray-400 text-lg mb-2">streaming.snaphubb — 1x mês</p>
            <p class="text-green-500 font-semibold text-xl">R$ 24,90 pago com sucesso</p>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-800 my-12"></div>

        <!-- Upsell Offer Section -->
        <div class="mb-12">
            <h3 class="text-center text-3xl font-bold mb-12">
                🎁 Espera! Você tem uma oferta <span class="text-red-600">EXCLUSIVA</span>
            </h3>

            <!-- Offer Card -->
            <div class="bg-gradient-to-br from-red-600/20 via-black to-black border border-red-600/40 rounded-2xl p-8 md:p-12 mb-8">
                <div class="mb-8">
                    <!-- Headline -->
                    <h4 class="text-2xl md:text-3xl font-bold mb-6 leading-tight">Descubra Quem São as Garotas que a Comunidade Está Elegendo como as "Melhores do Mês" (Sem Censura)</h4>
                    
                    <!-- Subtítulo -->
                    <div class="mb-6">
                        <p class="text-red-500 font-semibold text-lg mb-2">Adicione o "Filtro da Elite" ao seu pedido:</p>
                        <p class="text-gray-300">Tenha acesso imediato à playlist das garotas mais votadas pela comunidade e elimine 100% do risco de escolher conteúdo ruim.</p>
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

                    <!-- Corpo do Texto -->
                    <div class="mb-6 space-y-4">
                        <p class="text-gray-300">Você já garantiu seu acesso, mas deixe-me te fazer uma pergunta rápida:</p>
                        <p class="text-white font-semibold text-lg">Por que perder horas procurando o que assistir, se você pode ir direto ao "Pote de Ouro"?</p>
                        <p class="text-gray-300">No Painel das Garotas, nós removemos a dúvida.<br>Você não recebe apenas "mais conteúdo".<br><strong class="text-white">Você recebe a Nata da Nata</strong>, filtrada por quem mais entende do assunto: a própria comunidade.</p>
                    </div>

                    <!-- Como Funciona -->
                    <div class="mb-8">
                        <h5 class="text-xl font-bold text-white mb-4">Como funciona o Sistema de Elite:</h5>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0">🗳️</span>
                                <div>
                                    <p class="text-white font-semibold">Votação Ativa:</p>
                                    <p class="text-gray-300 text-sm">Durante o mês, milhares de membros votam na criadora que mais desejam ver.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0">🏆</span>
                                <div>
                                    <p class="text-white font-semibold">A Seleção:</p>
                                    <p class="text-gray-300 text-sm">A vencedora entra automaticamente na Playlist VIP <strong class="text-red-500">"AS MAIS VOTADAS"</strong>.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="text-2xl flex-shrink-0">🔥</span>
                                <div>
                                    <p class="text-white font-semibold">O Acesso:</p>
                                    <p class="text-gray-300 text-sm">Você recebe o catálogo completo com os vídeos mais quentes daquela criadora — sem precisar caçar links por aí.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Benefício Principal -->
                    <div class="bg-gradient-to-r from-yellow-600/20 to-yellow-500/10 border border-yellow-500/40 rounded-xl p-6 mb-8">
                        <h5 class="text-xl font-bold text-yellow-400 mb-3">O Grande Segredo:</h5>
                        <p class="text-white font-bold text-lg mb-3">Garantia Blindada de Acesso Vitalício.</p>
                        <p class="text-gray-300 text-sm mb-4">A maioria das assinaturas corta seu acesso assim que você para de pagar. Nós não.</p>
                        <p class="text-gray-300 text-sm mb-4">Ao adicionar isso ao seu pedido hoje, você garante uma vantagem "injusta":</p>
                        <p class="text-white font-semibold">Você pode cancelar a assinatura mensal e ainda assim continuar com acesso ao Painel de Votações, ao ranking e às Mais Votadas — enquanto o painel existir.</p>
                        <p class="text-gray-400 text-xs mt-4 italic">É a única assinatura que te recompensa pela fidelidade… e até pela desistência.</p>
                    </div>

                    <!-- Pricing Section -->
                    <div class="bg-black/50 border border-gray-700 rounded-xl p-6 mb-8">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <p class="text-gray-500 text-sm mb-1">Preço Original</p>
                                <p class="text-xl line-through text-gray-500">R$ {{ number_format($product['origin_price'] ?? 97, 2, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm mb-1">Desconto PIX</p>
                                <p class="text-xl font-bold text-red-500">-R$ {{ number_format((($product['origin_price'] ?? 97) - (($product['price'] ?? 3700)/100)), 2, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="border-t border-gray-700 pt-6">
                            <p class="text-gray-400 mb-2">Você vai pagar</p>
                            <div class="flex items-baseline gap-2">
                                <p class="text-5xl font-bold text-white">R$ {{ number_format(($product['price'] ?? 3700)/100, 2, ',', '.') }}</p>
                            </div>
                            <p class="text-green-500 font-semibold mt-4">💰 Você economiza R$ {{ number_format((($product['origin_price'] ?? 97) - (($product['price'] ?? 3700)/100)), 2, ',', '.') }}</p>
                        </div>
                    </div>

                    <!-- Urgency -->
                    <div class="bg-red-600/10 border border-red-600/30 rounded-lg p-4 mb-8 flex items-center gap-3">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <div>
                            <p class="text-red-500 font-semibold text-sm">⏰ Oferta válida por apenas 10 minutos</p>
                            <p class="text-gray-400 text-xs">Aproveite este desconto exclusivo agora</p>
                        </div>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="space-y-3">
                        <button wire:click="aproveOffer" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-lg transition-all duration-300 flex items-center justify-center gap-2 text-lg group">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Aproveitar Oferta Agora
                        </button>
                        <button wire:click="declineOffer" class="w-full bg-gray-900 hover:bg-gray-800 text-gray-300 font-semibold py-4 rounded-lg transition-all duration-300 border border-gray-700">
                            Não, continuar com minha compra
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
                            Pagamento 100% seguro com PIX
                        </p>
                        <p>✓ Sem compromisso • Cancele quando quiser • Suporte 24/7</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Proof -->
        <div class="text-center text-gray-500 text-sm">
            <p>✓ 2.847 pessoas aprovaram essa oferta nos últimos 7 dias</p>
            <p>⭐ 4.8/5 de satisfação</p>
        </div>
    </div>
    @endif

    <script>
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
    </script>
</div>