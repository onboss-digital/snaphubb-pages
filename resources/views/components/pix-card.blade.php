@props(['pixData', 'pixStatus', 'expiresAt'])

@php
$statusBadges = [
    'PENDING' => ['text' => 'Aguardando Pagamento', 'class' => 'bg-yellow-500 animate-pulse'],
    'PAID' => ['text' => 'Pagamento Confirmado! 🎉', 'class' => 'bg-green-500'],
    'EXPIRED' => ['text' => 'Expirado', 'class' => 'bg-red-500'],
    'FAILED' => ['text' => 'Falhou', 'class' => 'bg-red-500'],
];

$currentStatus = $statusBadges[$pixStatus] ?? $statusBadges['PENDING'];
@endphp

<div class="bg-[#1F1F1F] rounded-xl p-6 border-2 border-[#E50914]">
    <!-- Header com Status -->
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">PIX - AbacatePay</h3>
        <span class="px-3 py-1 rounded-full text-xs font-medium text-white {{ $currentStatus['class'] }}">
            {{ $currentStatus['text'] }}
        </span>
    </div>

    @if($pixStatus === 'PAID')
        <!-- Tela de Sucesso -->
        <div class="text-center py-8">
            <svg class="w-20 h-20 mx-auto text-green-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h4 class="text-xl font-bold text-green-500 mb-2">Pagamento Confirmado!</h4>
            <p class="text-gray-300 text-sm">Você será redirecionado em instantes...</p>
        </div>
    @elseif(in_array($pixStatus, ['EXPIRED', 'FAILED']))
        <!-- Tela de Erro/Expiração -->
        <div class="text-center py-8">
            <svg class="w-20 h-20 mx-auto text-red-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h4 class="text-xl font-bold text-red-500 mb-2">
                {{ $pixStatus === 'EXPIRED' ? 'Pagamento Expirado' : 'Pagamento Falhou' }}
            </h4>
            <p class="text-gray-300 text-sm mb-4">Você será redirecionado em instantes...</p>
            <button 
                wire:click="$set('pixData', null)"
                class="px-4 py-2 bg-[#E50914] text-white rounded-lg hover:bg-red-700 transition-all">
                Tentar Novamente
            </button>
        </div>
    @else
        <!-- Tela de Pagamento Pendente -->
        <div class="space-y-4">
            <!-- QR Code -->
            <div class="bg-white p-4 rounded-lg border-2 border-dashed border-gray-600 text-center">
                @if(isset($pixData['brCodeBase64']))
                    <img 
                        src="{{ $pixData['brCodeBase64'] }}" 
                        alt="QR Code PIX" 
                        class="mx-auto w-48 h-48 object-contain"
                        id="pix-qr-image"
                    />
                @else
                    <div class="w-48 h-48 mx-auto flex items-center justify-center bg-gray-200 rounded">
                        <span class="text-gray-500">Carregando QR Code...</span>
                    </div>
                @endif
                <p class="text-sm text-gray-700 mt-3 font-medium">
                    Escaneie o QR Code com o app do seu banco
                </p>
            </div>

            <!-- Informações do Pagamento -->
            <div class="bg-[#2D2D2D] rounded-lg p-4 space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Valor:</span>
                    <span class="text-white font-bold text-lg">
                        R$ {{ number_format(($pixData['amount'] ?? 0) / 100, 2, ',', '.') }}
                    </span>
                </div>
                @if(isset($pixData['pix_id']))
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-xs">ID:</span>
                    <span class="text-gray-300 text-xs font-mono">{{ $pixData['pix_id'] }}</span>
                </div>
                @endif
            </div>

            <!-- Contador Regressivo -->
            @if(isset($expiresAt))
            <div class="bg-red-900 bg-opacity-30 border border-red-600 rounded-lg p-3 text-center">
                <p class="text-red-400 text-sm mb-1">Expira em:</p>
                <p class="text-white font-bold text-xl" id="pix-countdown" data-expires-at="{{ $expiresAt }}">
                    Calculando...
                </p>
            </div>
            @endif

            <!-- Código Copia e Cola -->
            <div class="bg-[#2D2D2D] rounded-lg p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm font-medium">Copia e Cola:</span>
                    <button 
                        type="button"
                        onclick="copyPixCode()"
                        class="flex items-center space-x-1 px-3 py-1 bg-[#E50914] text-white rounded text-xs hover:bg-red-700 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span>Copiar</span>
                    </button>
                </div>
                <div class="bg-[#1F1F1F] rounded p-2 break-all text-xs text-gray-300 font-mono max-h-24 overflow-y-auto" id="pix-brcode">
                    {{ $pixData['brCode'] ?? 'Carregando...' }}
                </div>
            </div>

            <!-- Botão "Já Paguei" -->
            <button 
                type="button"
                wire:click="checkPixStatus"
                class="w-full py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-all flex items-center justify-center space-x-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Já Paguei</span>
            </button>

            <!-- Informações Adicionais -->
            <div class="text-center text-xs text-gray-400 space-y-1">
                <p>✅ Pagamento 100% seguro via PIX</p>
                <p>⚡ Confirmação instantânea</p>
                <p>🔒 Dados protegidos com criptografia</p>
            </div>
        </div>
    @endif
</div>

<script>
// Função para copiar código PIX
function copyPixCode() {
    const brCode = document.getElementById('pix-brcode').innerText;
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(brCode).then(() => {
            // Feedback visual
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg><span>Copiado!</span>';
            button.classList.add('bg-green-600');
            button.classList.remove('bg-[#E50914]');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-600');
                button.classList.add('bg-[#E50914]');
            }, 2000);
        }).catch(err => {
            console.error('Erro ao copiar:', err);
            alert('Erro ao copiar. Tente selecionar e copiar manualmente.');
        });
    } else {
        // Fallback para navegadores antigos
        const textArea = document.createElement('textarea');
        textArea.value = brCode;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('Código copiado!');
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
        countdownEl.textContent = 'Expirado';
        countdownEl.classList.add('text-red-500');
        return;
    }
    
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    countdownEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// Atualizar contador a cada segundo
if (document.getElementById('pix-countdown')) {
    updatePixCountdown();
    setInterval(updatePixCountdown, 1000);
}
</script>
