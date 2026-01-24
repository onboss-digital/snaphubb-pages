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
        // Identifiers válidos: voting, voting-br, voting-en, voting-es, community-voting
        if (is_array($this->plans) && !empty($this->plans)) {
            $firstPlan = reset($this->plans);
            \Illuminate\Support\Facades\Log::info('PainelVotingCheckout: Plans estrutura completa', [
                'type' => gettype($this->plans),
                'count' => count($this->plans),
                'keys' => array_keys($this->plans),
                'first_plan_full' => json_encode($firstPlan, JSON_UNESCAPED_UNICODE),
            ]);
            
            $this->plans = array_filter($this->plans, function($plan) {
                // Log completo da estrutura do plano
                \Illuminate\Support\Facades\Log::info('PainelVotingCheckout: Estrutura do plano', [
                    'plan_json' => json_encode($plan, JSON_UNESCAPED_UNICODE),
                    'plan_keys' => is_array($plan) ? array_keys($plan) : 'NOT_ARRAY',
                ]);
                
                // Usar identifier primeiro (vem do banco), depois id (Stripe)
                $identifier = strtolower($plan['identifier'] ?? $plan['id'] ?? '');
                $name = strtolower($plan['name'] ?? '');
                
                \Illuminate\Support\Facades\Log::info('PainelVotingCheckout: Filtrando plano', [
                    'identifier' => $identifier,
                    'name' => $name,
                    'matches_voting' => str_contains($identifier, 'voting'),
                    'matches_name' => str_contains($name, 'voting')
                ]);
                
                return (
                    str_contains($identifier, 'voting') ||
                    str_contains($name, 'voting') ||
                    str_contains($name, 'painel das garotas') ||
                    str_contains($name, 'painel')
                );
            });

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
