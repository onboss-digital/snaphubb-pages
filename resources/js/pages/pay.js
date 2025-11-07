import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import utilsScript from 'intl-tel-input/build/js/utils.js';
import IMask from 'imask';
import EmailValidator from 'email-deep-validator';

function setupIntlTelInput(selector, livewireEventName) {
    const inputs = document.querySelectorAll(selector);
    inputs.forEach(input => {
        if (input.iti) { // Evita reinicialização
            return;
        }

        const iti = intlTelInput(input, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch("https://ipapi.co/json")
                    .then(res => res.json())
                    .then(data => callback(data.country_code))
                    .catch(() => callback("br"));
            },
            utilsScript: utilsScript,
            nationalMode: true,
            formatOnDisplay: true,
        });

        input.iti = iti; // Armazena a instância para verificar se já existe

        const changeHandler = () => {
            const fullNumber = iti.isValidNumber() ? iti.getNumber() : '';
            Livewire.dispatch(livewireEventName, {
                phone: fullNumber
            });
        };

        input.addEventListener('change', changeHandler);
        input.addEventListener('countrychange', changeHandler);
    });
}

function setupMasksAndValidators() {
    // Máscara de CPF para o formulário PIX
    const cpfInput = document.querySelector('input[name="pix_cpf"]');
    if (cpfInput && !cpfInput.imask) {
        IMask(cpfInput, { mask: '000.000.000-00' });
    }

    // Validador de E-mail para o formulário PIX
    const emailInput = document.querySelector('input[name="pix_email"]');
    if (emailInput && !emailInput.hasAttribute('data-validator-attached')) {
        emailInput.setAttribute('data-validator-attached', 'true');
        const emailValidator = new EmailValidator();
        let emailSuggestion = emailInput.parentNode.querySelector('.email-suggestion');

        if (!emailSuggestion) {
            emailSuggestion = document.createElement('div');
            emailSuggestion.className = 'text-xs text-yellow-400 mt-1 email-suggestion';
            emailInput.parentNode.appendChild(emailSuggestion);
        }

        emailInput.addEventListener('blur', async () => {
            const email = emailInput.value;
            emailSuggestion.textContent = '';
            if (email) {
                try {
                    const { wellFormed, validDomain, validMailbox } = await emailValidator.verify(email);
                    if (wellFormed && validDomain && validMailbox === false) {
                        emailSuggestion.textContent = 'E-mail parece inválido. Verifique, por favor.';
                    }
                } catch (error) {
                    console.warn("Falha na verificação do e-mail:", error);
                }
            }
        });
    }
}

document.addEventListener('livewire:init', () => {
    let pixPollingInterval = null;

    function initializeAll() {
        // Configura o intl-tel-input para o telefone do formulário principal
        setupIntlTelInput("input[name='phone']", 'updatePhone');
        // E agora também para o telefone do modal PIX
        setupIntlTelInput("input[name='pix_phone']", 'updatePixPhone');
        // Configura as máscaras para o modal PIX
        setupMasksAndValidators();
    }

    // Carga inicial
    initializeAll();

    // Após cada atualização do Livewire
    Livewire.hook('message.processed', (message, component) => {
        initializeAll();
    });

    // --- Lógica de Polling do PIX ---
    Livewire.on('start-pix-polling', () => {
        if (pixPollingInterval) clearInterval(pixPollingInterval);
        pixPollingInterval = setInterval(() => {
            Livewire.dispatch('checkPixPaymentStatus');
        }, 3000);
    });

    Livewire.on('stop-pix-polling', () => {
        if (pixPollingInterval) clearInterval(pixPollingInterval);
    });
});
