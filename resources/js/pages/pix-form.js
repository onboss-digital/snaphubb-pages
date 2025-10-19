import 'intl-tel-input/build/css/intlTelInput.css';
import intlTelInput from 'intl-tel-input';
import EmailValidator from 'email-deep-validator';

document.addEventListener('livewire:init', () => {
    const phoneInput = document.querySelector('input[name="pix_phone"]');
    if (phoneInput) {
        const iti = intlTelInput(phoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch("https://ipapi.co/json")
                    .then(function(res) { return res.json(); })
                    .then(function(data) { callback(data.country_code); })
                    .catch(function() { callback("us"); });
            },
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        });

        phoneInput.addEventListener('change', () => {
            if (iti.isValidNumber()) {
                Livewire.dispatch('updatePhone', { phone: iti.getNumber() });
            }
        });
    }

    const emailInput = document.querySelector('input[name="pix_email"]');
    if (emailInput) {
        emailInput.addEventListener('blur', async () => {
            const emailValidator = new EmailValidator();
            const { wellFormed, validDomain, validMailbox } = await emailValidator.verify(emailInput.value);
            if (wellFormed && validDomain && !validMailbox) {
                // This is a simplified example. A real implementation would need a more robust suggestion engine.
                const suggestion = emailInput.value.replace(/gmial\.com$/, 'gmail.com');
                if (suggestion !== emailInput.value) {
                    // You could display a suggestion to the user here.
                    console.log(`Did you mean ${suggestion}?`);
                }
            }
        });
    }
});
