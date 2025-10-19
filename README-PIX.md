# Configuração da Funcionalidade de Pagamento PIX com Mercado Pago

Este documento descreve os passos necessários para configurar e ativar a funcionalidade de pagamento via PIX com o Mercado Pago no projeto.

## Pré-requisitos

- Acesso ao ambiente de desenvolvimento do projeto.
- Uma conta de desenvolvedor no Mercado Pago com as credenciais de acesso (access token).

## Variáveis de Ambiente

Para que a integração com o Mercado Pago funcione corretamente, é necessário adicionar as seguintes variáveis de ambiente ao arquivo `.env` do projeto:

```
MERCADOPAGO_ACCESS_TOKEN=SEU_ACCESS_TOKEN_AQUI
```

Substitua `SEU_ACCESS_TOKEN_AQUI` pelo seu token de acesso do Mercado Pago. Este token é essencial para autenticar as requisições à API do Mercado Pago.

## Configuração do Gateway de Pagamento

A integração está configurada para usar o gateway do Mercado Pago quando especificado. Para ativar o PIX como método de pagamento, certifique-se de que o `PaymentGatewayFactory` está configurado para instanciar o `MercadoPago` gateway.

O seletor de método de pagamento no checkout irá automaticamente mostrar a opção de PIX quando o idioma da página estiver configurado para português do Brasil.

## Fluxo de Pagamento

1.  **Seleção do Método de Pagamento**: No checkout, o usuário seleciona "PIX (Mercado Pago)".
2.  **Preenchimento dos Dados**: O usuário preenche os campos de nome, e-mail e telefone.
3.  **Geração do PIX**: Ao clicar em "Gerar PIX", o sistema envia uma requisição para a API do Mercado Pago.
4.  **Exibição do QR Code**: Um modal é exibido com o QR Code e o código "copia e cola" para o pagamento.
5.  **Verificação de Status**: O sistema verifica automaticamente o status do pagamento a cada 3 segundos.
6.  **Redirecionamento**:
    -   **Sucesso**: Se o pagamento for aprovado, o usuário é redirecionado para a página de sucesso (`/obg-br`).
    -   **Falha**: Se ocorrer um erro, uma mensagem de erro é exibida.

## Testes

Para testar a funcionalidade em um ambiente local, certifique-se de que as variáveis de ambiente estão configuradas corretamente e que o servidor de desenvolvimento está em execução. Use dados de teste para preencher o formulário PIX e gerar um QR Code de teste.
