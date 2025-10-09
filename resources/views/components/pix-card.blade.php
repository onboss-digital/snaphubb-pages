@props(['pixData', 'pixStatus', 'expiresAt'])

@php
$statusBadges = [
    'PENDING' => ['text' => 'Aguardando Pagamento', 'class' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/50'],
    'PAID' => ['text' => 'Pagamento Confirmado! 🎉', 'class' => 'bg-green-500/20 text-green-400 border-green-500/50'],
    'EXPIRED' => ['text' => 'Expirado', 'class' => 'bg-red-500/20 text-red-400 border-red-500/50'],
    'FAILED' => ['text' => 'Falhou', 'class' => 'bg-red-500/20 text-red-400 border-red-500/50'],
];

$currentStatus = $statusBadges[$pixStatus] ?? $statusBadges['PENDING'];
@endphp

<style>
/* Efeito Neon Verde */
.neon-green {
    color: #00FF88;
    text-shadow: 
        0 0 5px #00FF88,
        0 0 10px #00FF88,
        0 0 20px #00FF88,
        0 0 40px #00FF88;
}

.neon-green-glow {
    filter: drop-shadow(0 0 5px #00FF88) 
            drop-shadow(0 0 10px #00FF88) 
            drop-shadow(0 0 15px #00FF88);
}

.pix-card-container {
    background: linear-gradient(135deg, #1F1F1F 0%, #2D2D2D 100%);
    border: 2px solid transparent;
    background-clip: padding-box;
    position: relative;
}

.pix-card-container::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, #00FF88 0%, #00CC6A 100%);
    border-radius: 1rem;
    z-index: -1;
    opacity: 0.3;
}

.pix-card-container:hover::before {
    opacity: 0.5;
    transition: opacity 0.3s ease;
}

@keyframes pulse-glow {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

.pulse-glow {
    animation: pulse-glow 2s ease-in-out infinite;
}

.qr-code-container {
    background: white;
    padding: 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 4px 20px rgba(0, 255, 136, 0.2);
    position: relative;
}

.qr-code-container::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, #00FF88, #00CC6A);
    border-radius: 1rem;
    z-index: -1;
}

.copy-button {
    background: linear-gradient(135deg, #00FF88 0%, #00CC6A 100%);
    transition: all 0.3s ease;
}

.copy-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 255, 136, 0.4);
}

.copy-button:active {
    transform: translateY(0);
}
</style>

<div class="pix-card-container rounded-xl p-6 shadow-2xl">
    <!-- Header com Logo PIX Neon -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-3">
            <!-- Logo PIX SVG com Neon Verde -->
            <svg class="w-12 h-12 neon-green-glow" viewBox="0 0 512 512" fill="#00FF88" xmlns="http://www.w3.org/2000/svg">
                <path d="M242.4 292.5C247.8 287.1 257.1 287.1 262.5 292.5L339.5 369.5C353.7 383.7 372.6 391.5 392.6 391.5H407.7L310.6 488.6C280.3 518.1 231.1 518.1 200.8 488.6L103.3 391.5H112.6C132.6 391.5 151.5 383.7 165.7 369.5L242.4 292.5zM262.5 218.9C257.1 224.3 247.8 224.3 242.4 218.9L165.7 142.1C151.5 127.9 132.6 120.1 112.6 120.1H103.3L200.7 22.76C231.1-7.586 280.3-7.586 310.6 22.76L407.7 120.1H392.6C372.6 120.1 353.7 127.9 339.5 142.1L262.5 218.9zM112.6 142.1C126.4 142.1 139.1 148.3 149.7 158.1L226.4 234.8C233.6 241.1 243 245.6 252.5 245.6C261.9 245.6 271.3 241.1 278.5 234.8L355.5 157.8C365.3 148.1 378.8 142.1 392.6 142.1H430.3L488.6 200.8C518.9 231.1 518.9 280.3 488.6 310.6L430.3 368.9H392.6C378.8 368.9 365.3 362.9 355.5 353.1L278.5 276.1C264.6 262.2 240.3 262.2 226.4 276.1L149.7 352.8C139.1 362.6 126.4 368.6 112.6 368.6H80.78L22.76 310.6C-7.586 280.3-7.586 231.1 22.76 200.8L80.78 142.1H112.6z"/>
            </svg>
            
            <!-- Texto PIX com Neon -->
            <div>
                <h3 class="text-2xl font-bold neon-green tracking-wider">PIX</h3>
                <p class="text-xs text-gray-400">Pagamento Instantâneo</p>
            </div>
        </div>
        
        <!-- Badge de Status -->
        <span class="px-4 py-2 rounded-full text-xs font-semibold border {{ $currentStatus['class'] }}">
            {{ $currentStatus['text'] }}
        </span>
    </div>

    @if($pixStatus === 'PAID')
        <!-- Tela de Sucesso -->
        <div class="text-center py-12">
            <div class="mb-6">
                <svg class="w-24 h-24 mx-auto neon-green-glow" fill="#00FF88" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h4 class="text-2xl font-bold neon-green mb-3">Pagamento Confirmado!</h4>
            <p class="text-gray-300 text-sm">Você será redirecionado em instantes...</p>
            <div class="mt-6">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-green-500"></div>
            </div>
        </div>
        
    @elseif(in_array($pixStatus, ['EXPIRED', 'FAILED']))
        <!-- Tela de Erro/Expiração -->
        <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-red-500 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h4 class="text-2xl font-bold text-red-500 mb-3">
                {{ $pixStatus === 'EXPIRED' ? 'Pagamento Expirado' : 'Pagamento Falhou' }}
            </h4>
            <p class="text-gray-300 text-sm mb-6">Por favor, tente novamente</p>
            <button 
                wire:click="$set('pixData', null)"
                class="px-6 py-3 bg-[#E50914] text-white rounded-lg font-semibold hover:bg-red-700 transition-all shadow-lg">
                Gerar Novo PIX
            </button>
        </div>
        
    @else
        <!-- Tela de Pagamento Pendente -->
        <div class="space-y-6">
            
            <!-- QR Code com Destaque -->
            <div class="qr-code-container">
                @if(isset($pixData['brCodeBase64']))
                    <img 
                        src="{{ $pixData['brCodeBase64'] }}" 
                        alt="QR Code PIX" 
                        class="mx-auto w-56 h-56 object-contain pulse-glow"
                        id="pix-qr-image"
                    />
                @else
                    <div class="w-56 h-56 mx-auto flex items-center justify-center bg-gray-100 rounded-lg">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-green-500 mb-3"></div>
                            <p class="text-gray-600 text-sm">Gerando QR Code...</p>
                        </div>
                    </div>
                @endif
                <p class="text-center text-sm text-gray-700 mt-4 font-semibold">
                    📱 Escaneie com o app do seu banco
                </p>
            </div>

            <!-- Valor e ID -->
            <div class="bg-[#1F1F1F] rounded-xl p-5 border border-gray-700">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-gray-400 text-sm font-medium">Valor a pagar:</span>
                    <span class="text-white font-bold text-2xl neon-green">
                        R$ {{ number_format(($pixData['amount'] ?? 0) / 100, 2, ',', '.') }}
                    </span>
                </div>
                @if(isset($pixData['pix_id']))
                <div class="flex justify-between items-center pt-3 border-t border-gray-700">
                    <span class="text-gray-500 text-xs">ID da Transação:</span>
                    <span class="text-gray-400 text-xs font-mono">{{ $pixData['pix_id'] }}</span>
                </div>
                @endif
            </div>

            <!-- Contador Regressivo -->
            @if(isset($expiresAt))
            <div class="bg-gradient-to-r from-red-900/30 to-orange-900/30 border-2 border-red-500/50 rounded-xl p-4 text-center">
                <div class="flex items-center justify-center space-x-2 mb-2">
                    <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-red-400 text-sm font-semibold">Tempo restante:</p>
                </div>
                <p class="text-white font-bold text-3xl tabular-nums" id="pix-countdown" data-expires-at="{{ $expiresAt }}">
                    --:--
                </p>
            </div>
            @endif

            <!-- Código Copia e Cola -->
            <div class="bg-[#1F1F1F] rounded-xl p-5 border border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-gray-300 text-sm font-semibold">Código Pix Copia e Cola</span>
                    </div>
                    <button 
                        type="button"
                        onclick="copyPixCode()"
                        class="copy-button flex items-center space-x-2 px-4 py-2 text-black rounded-lg font-semibold text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span>Copiar</span>
                    </button>
                </div>
                <div class="bg-black/50 rounded-lg p-3 border border-gray-700">
                    <p class="text-gray-300 font-mono text-xs break-all leading-relaxed max-h-32 overflow-y-auto" id="pix-brcode">
                        {{ $pixData['brCode'] ?? 'Carregando código...' }}
                    </p>
                </div>
            </div>

            <!-- Botão "Já Paguei" -->
            <button 
                type="button"
                wire:click="checkPixStatus"
                class="w-full py-4 bg-gradient-to-r from-green-600 to-green-500 text-white rounded-xl font-bold text-lg hover:from-green-500 hover:to-green-400 transition-all shadow-lg hover:shadow-green-500/50 flex items-center justify-center space-x-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Já Paguei</span>
            </button>

            <!-- Informações de Segurança -->
            <div class="grid grid-cols-3 gap-3 text-center pt-4 border-t border-gray-700">
                <div class="flex flex-col items-center space-y-1">
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span class="text-xs text-gray-400">100% Seguro</span>
                </div>
                <div class="flex flex-col items-center space-y-1">
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span class="text-xs text-gray-400">Instantâneo</span>
                </div>
                <div class="flex flex-col items-center space-y-1">
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span class="text-xs text-gray-400">Criptografado</span>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
// Função para copiar código PIX
function copyPixCode() {
    const brCode = document.getElementById('pix-brcode').innerText.trim();
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(brCode).then(() => {
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            
            button.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg><span>Copiado!</span>';
            button.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.style.background = 'linear-gradient(135deg, #00FF88 0%, #00CC6A 100%)';
            }, 2000);
        }).catch(err => {
            console.error('Erro ao copiar:', err);
            alert('Erro ao copiar. Tente selecionar e copiar manualmente.');
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = brCode;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg><span>Copiado!</span>';
            setTimeout(() => {
                button.innerHTML = originalHTML;
            }, 2000);
        } catch (err) {
            alert('Erro ao copiar. Tente selecionar e copiar manualmente.');
        }
        
        document.body.removeChild(textArea);
    }
}

// Contador regressivo
function updatePixCountdown() {
    const countdownEl = document.getElementById('pix-countdown');
    if (!countdownEl) return;
    
    const expiresAt = countdownEl.getAttribute('data-expires-at');
    if (!expiresAt) return;
    
    const expiryTime = new Date(expiresAt).getTime();
    const now = new Date().getTime();
    const distance = expiryTime - now;
    
    if (distance < 0) {
        countdownEl.textContent = '00:00';
        countdownEl.classList.add('text-red-500');
        return;
    }
    
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    countdownEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// Inicializar contador
if (document.getElementById('pix-countdown')) {
    updatePixCountdown();
    setInterval(updatePixCountdown, 1000);
}
</script>
