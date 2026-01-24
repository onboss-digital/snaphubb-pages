<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use App\Interfaces\PaymentGatewayInterface;

/**
 * StreamingCheckout - Componente para compra de planos de Streaming
 * Estende PagePay mas filtra apenas planos de streaming (excluindo voting)
 */
class StreamingCheckout extends PagePay
{
    /**
     * Mount - Sobrescreve o mount do PagePay para filtrar apenas planos de streaming
     */
    public function mount(?PaymentGatewayInterface $paymentGateway = null)
    {
        // Chamar o mount pai primeiro
        parent::mount($paymentGateway);

        // FILTRAR: Manter apenas planos de streaming (excluir voting)
        if (is_array($this->plans) && !empty($this->plans)) {
            $this->plans = array_filter($this->plans, function($plan, $key) {
                $identifier = strtolower($key);
                $label = strtolower($plan['label'] ?? '');
                
                \Illuminate\Support\Facades\Log::info('StreamingCheckout: Filtrando plano', [
                    'key' => $key,
                    'identifier' => $identifier,
                    'label' => $label,
                    'is_streaming' => !str_contains($identifier, 'voting'),
                    'is_voting' => str_contains($identifier, 'voting')
                ]);
                
                // Excluir planos que contenham "voting"
                return !str_contains($identifier, 'voting') && !str_contains($label, 'voting');
            }, ARRAY_FILTER_USE_BOTH);

            // Se ainda temos planos, selecionar o primeiro
            if (!empty($this->plans)) {
                $first = array_key_first($this->plans);
                $this->selectedPlan = $first;
                
                \Illuminate\Support\Facades\Log::info('StreamingCheckout: Plano de streaming selecionado', [
                    'selectedPlan' => $this->selectedPlan,
                    'totalPlans' => count($this->plans),
                    'planos_disponiveis' => array_keys($this->plans)
                ]);
            } else {
                \Illuminate\Support\Facades\Log::warning('StreamingCheckout: Nenhum plano de streaming encontrado', [
                    'plans_recebidos' => array_keys($this->plans ?? [])
                ]);
            }
        }
    }
}
