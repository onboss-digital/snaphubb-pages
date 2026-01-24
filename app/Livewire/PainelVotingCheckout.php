<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use App\Interfaces\PaymentGatewayInterface;

/**
 * PainelVotingCheckout - Componente para compra única (vitalícia) do Painel de Voting
 * Estende PagePay mas filtra apenas planos de voting
 */
class PainelVotingCheckout extends PagePay
{
    /**
     * Mount - Sobrescreve o mount do PagePay para filtrar apenas planos de voting
     */
    public function mount(?PaymentGatewayInterface $paymentGateway = null)
    {
        // Chamar o mount pai primeiro
        parent::mount($paymentGateway);

        // FILTRAR: Manter apenas planos de voting
        // A chave do array é o identificador: painel_das_garotas_-_voting
        // O label contém o nome do plano
        if (is_array($this->plans) && !empty($this->plans)) {
            $firstKey = array_key_first($this->plans);
            $firstPlan = reset($this->plans);
            \Illuminate\Support\Facades\Log::info('PainelVotingCheckout: Plans estrutura', [
                'count' => count($this->plans),
                'keys' => array_keys($this->plans),
                'first_key' => $firstKey,
                'first_label' => $firstPlan['label'] ?? 'N/A',
            ]);
            
            // Usar ARRAY_FILTER_USE_BOTH para ter acesso tanto à chave quanto ao valor
            $this->plans = array_filter($this->plans, function($plan, $key) {
                $identifier = strtolower($key);
                $label = strtolower($plan['label'] ?? '');
                
                \Illuminate\Support\Facades\Log::info('PainelVotingCheckout: Filtrando plano', [
                    'key' => $key,
                    'identifier' => $identifier,
                    'label' => $label,
                    'matches_voting_key' => str_contains($identifier, 'voting'),
                    'matches_voting_label' => str_contains($label, 'voting')
                ]);
                
                return str_contains($identifier, 'voting') || str_contains($label, 'voting');
            }, ARRAY_FILTER_USE_BOTH);

            // Se ainda temos planos, selecionar o primeiro
            if (!empty($this->plans)) {
                $first = array_key_first($this->plans);
                $this->selectedPlan = $first;
                
                \Illuminate\Support\Facades\Log::info('PainelVotingCheckout: Plano de voting selecionado', [
                    'selectedPlan' => $this->selectedPlan,
                    'totalPlans' => count($this->plans),
                    'planos_disponiveis' => array_keys($this->plans)
                ]);
            } else {
                \Illuminate\Support\Facades\Log::warning('PainelVotingCheckout: Nenhum plano de voting encontrado', [
                    'plans_recebidos' => array_keys($this->plans ?? [])
                ]);
            }
        }
    }

    /**
     * Render - Usa a mesma view do PagePay
     * Não precisa de render() customizado - herda do PagePay
     */
}
