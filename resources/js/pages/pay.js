import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import utilsScript from 'intl-tel-input/build/js/utils.js';
import IMask from 'imask';
import EmailValidator from 'email-deep-validator';

function setupIntlTelInput(selector, livewireEventName) {
    const inputs = document.querySelectorAll(selector);
    inputs.forEach(input => {
        if (input.iti) return;

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

        const changeHandler = () => {
            const fullNumber = iti.isValidNumber() ? iti.getNumber() : '';
            Livewire.dispatch(livewireEventName, { phone: fullNumber });
        };

        input.addEventListener('change', changeHandler);
        input.addEventListener('countrychange', changeHandler);
    });
}

function setupMasksAndValidators() {
    const cpfInput = document.querySelector('input[name="pix_cpf"]');
    if (cpfInput && !cpfInput.imask) {
        cpfInput.imask = IMask(cpfInput, { mask: '000.000.000-00' });
    }

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

    const hideClientPixLoader = () => {
        const loader = document.getElementById('client-pix-loader');
        if (!loader) return;

        loader.style.transition = 'opacity 0.5s ease-out';
        loader.style.opacity = '0';

        setTimeout(() => {
            loader.classList.add('hidden');
            loader.style.display = 'none';
        }, 500);
    };

    function initializeAll() {
        setupIntlTelInput("input[name='phone']", 'updatePhone');
        setupIntlTelInput("input[name='pix_phone']", 'updatePixPhone');
        setupMasksAndValidators();
    }

    initializeAll();

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            initializeAll();
        });
    });

    Livewire.listen('start-pix-polling', () => {
        if (pixPollingInterval) clearInterval(pixPollingInterval);
        pixPollingInterval = setInterval(() => {
            Livewire.dispatch('checkPixPaymentStatus');
        }, 3000);
    });

    Livewire.listen('stop-pix-polling', () => {
        if (pixPollingInterval) clearInterval(pixPollingInterval);
    });

    Livewire.listen('pix-ready', (payload = {}) => {
        console.log('[JS] PIX modal should now be visible', payload);

        hideClientPixLoader();

        try {
            if (typeof window.startTimer === 'function') {
                const timerEl = document.getElementById('pix-timer');
                if (timerEl) {
                    if (typeof window.pixQRTimer !== 'undefined') window.pixQRTimer = 300;
                    window.startTimer(timerEl);
                }
            }
        } catch (err) {
            console.warn('[JS] pix-ready: could not start timer', err);
        }
    });
});
