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
        // EmailValidator might not be suitable for frontend bundle directly or needs polyfills,
        // keeping original logic but wrapping in try-catch to be safe.
        // Assuming EmailValidator is available from imports.

        try {
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
        } catch (e) {
            console.warn('EmailValidator init failed', e);
        }
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

    // Initial setup
    initializeAll();

    // Re-initialize after Livewire updates DOM
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            // Wait for DOM to be updated
            setTimeout(initializeAll, 50);
        });
    });

    // Also hook into navigated for SPA transitions if used
    document.addEventListener('livewire:navigated', () => {
        initializeAll();
    });

    // Replace Livewire.listen with Livewire.on for v3
    Livewire.on('start-pix-polling', () => {
        console.log('[JS] Starting PIX polling...');
        if (pixPollingInterval) clearInterval(pixPollingInterval);

        // Immediate check
        Livewire.dispatch('checkPixPaymentStatus');

        pixPollingInterval = setInterval(() => {
            console.log('[JS] Polling checkPixPaymentStatus...');
            Livewire.dispatch('checkPixPaymentStatus');
        }, 3000);
    });

    Livewire.on('stop-pix-polling', () => {
        console.log('[JS] Stopping PIX polling.');
        if (pixPollingInterval) clearInterval(pixPollingInterval);
        pixPollingInterval = null;
    });

    Livewire.on('pix-ready', (payload = {}) => {
        console.log('[JS] PIX modal should now be visible', payload);

        hideClientPixLoader();

        try {
            if (typeof window.startTimer === 'function') {
                const timerEl = document.getElementById('pix-timer');
                // The timer logic might be inside the blade view or a global script.
                // If it relies on DOM elements being present, they should be there now.
                // We trigger a custom event or call global function if it exists.
                if (timerEl) {
                    if (typeof window.pixQRTimer !== 'undefined') window.pixQRTimer = 300;
                    window.startTimer(timerEl);
                }
            }
        } catch (err) {
            console.warn('[JS] pix-ready: could not start timer', err);
        }
    });

    Livewire.on('pix-expired', () => {
        console.log('[JS] PIX expired event received');
        if (pixPollingInterval) clearInterval(pixPollingInterval);
        pixPollingInterval = null;
    });
});
