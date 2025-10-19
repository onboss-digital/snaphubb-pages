import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import utilsScript from 'intl-tel-input/build/js/utils.js';
import IMask from 'imask';
import EmailValidator from 'email-deep-validator';

function setupIntlTelInput(selector, livewireEventName) {
    const inputs = document.querySelectorAll(selector);
    inputs.forEach(input => {
        if (input.iti) {
            input.iti.destroy();
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

        input.iti = iti;

        input.addEventListener('input', () => {
            if (typeof intlTelInputUtils !== 'undefined') {
                const currentNumber = iti.getNumber(intlTelInputUtils.numberFormat.NATIONAL);
                if (input.value !== currentNumber) {
                    input.value = currentNumber;
                }
            }
        });

        input.addEventListener('change', () => {
            if (iti.isValidNumber()) {
                const fullNumber = iti.getNumber();
                Livewire.dispatch(livewireEventName, {
                    phone: fullNumber
                });
            }
        });
    });
}

document.addEventListener('livewire:init', () => {
    let pixPollingInterval = null;

    function initializeAllPhoneInputs() {
        setupIntlTelInput("input[name='phone']", 'updatePhone');
        setupIntlTelInput("input[name='pix_phone']", 'updatePixPhone');
    }

    initializeAllPhoneInputs();

    Livewire.hook('message.processed', (message, component) => {
        initializeAllPhoneInputs();
    });

    Livewire.on('start-pix-polling', () => {
        if (pixPollingInterval) {
            clearInterval(pixPollingInterval);
        }
        pixPollingInterval = setInterval(() => {
            Livewire.dispatch('checkPixPaymentStatus');
        }, 3000);
    });

    Livewire.on('stop-pix-polling', () => {
        if (pixPollingInterval) {
            clearInterval(pixPollingInterval);
        }
    });

    const emailValidator = new EmailValidator();
    const emailInput = document.querySelector('input[name="pix_email"]');
    const emailSuggestion = document.createElement('div');
    emailSuggestion.className = 'text-xs text-yellow-400 mt-1';
    emailInput.parentNode.appendChild(emailSuggestion);

    emailInput.addEventListener('blur', async () => {
        const email = emailInput.value;
        if (email) {
            const { wellFormed, validDomain, validMailbox } = await emailValidator.verify(email);
            if (wellFormed && validDomain && !validMailbox) {
                emailSuggestion.textContent = 'Did you mean a different email?';
            } else {
                emailSuggestion.textContent = '';
            }
        }
    });

    const cpfInput = document.querySelector('input[name="pix_cpf"]');
    const cpfMask = {
        mask: '000.000.000-00'
    };
    const mask = IMask(cpfInput, cpfMask);
});
