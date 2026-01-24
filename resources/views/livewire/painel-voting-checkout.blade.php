{{-- View para PainelVotingCheckout - Checkout do Painel de Voting (compra única, vitalícia) --}}
@extends('layouts.app')

@section('content')
<div class="painel-voting-checkout container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            {{-- Header --}}
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="ph ph-star text-warning me-2"></i>
                    Painel das Garotas - Voting
                </h1>
                <p class="lead text-muted mb-4">
                    Acesso vitalício para participar das votações semanais da comunidade
                </p>
                
                {{-- Benefícios --}}
                <div class="benefits row g-3 mb-5">
                    <div class="col-md-4">
                        <div class="benefit-item">
                            <i class="ph ph-heart-half display-6 text-danger mb-2"></i>
                            <h5>Vote Ilimitadamente</h5>
                            <p class="small text-muted">Participe de todas as votações</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benefit-item">
                            <i class="ph ph-chart-line-up display-6 text-success mb-2"></i>
                            <h5>Veja o Top 3</h5>
                            <p class="small text-muted">Ranking ao vivo da semana</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benefit-item">
                            <i class="ph ph-users-three display-6 text-info mb-2"></i>
                            <h5>Influencie a Comunidade</h5>
                            <p class="small text-muted">Seu voto importa!</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Componente Livewire do PagePay (aqui renderiza o checkout) --}}
            <div class="checkout-section">
                {{-- Usar a view parent do PagePay, não renderizar o componente novamente --}}
                @include('livewire.page-pay')
            </div>

            {{-- Footer Info --}}
            <div class="text-center mt-5">
                <p class="text-muted small">
                    ✅ Acesso vitalício | 🔒 Pagamento seguro com Stripe | 🌍 Aceita Cartão e PIX
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    .benefit-item {
        padding: 20px;
        border-radius: 8px;
        background: #f8f9fa;
        transition: transform 0.3s ease;
    }

    .benefit-item:hover {
        transform: translateY(-5px);
        background: #e9ecef;
    }

    .checkout-section {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
</style>
@endsection
