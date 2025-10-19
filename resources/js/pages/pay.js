import intlTelInput from 'intl-tel-input';
import './pix-form.js';

document.addEventListener('livewire:init', () => {
    let iti = null;
    const phoneInput = document.querySelector("input[name='phone']");

    function initializePhoneInput() {
        if (iti) {
            iti.destroy();
        }

        if (phoneInput) {
            iti = intlTelInput(phoneInput, {
                initialCountry: "auto",
                geoIpLookup: function(callback) {
                    fetch("https://ipapi.co/json")
                        .then(res => res.json())
                        .then(data => callback(data.country_code))
                        .catch(() => callback("us"));
                },
                utilsScript: "/build/js/utils.js"
            });

            phoneInput.addEventListener('change', () => {
                if (iti.isValidNumber()) {
                    Livewire.dispatch('updatePhone', { phone: iti.getNumber() });
                }
            });
        }
    }

    // Initial call
    initializePhoneInput();

    // Re-initialize on every Livewire update
    Livewire.hook('message.processed', (message, component) => {
        initializePhoneInput();
    });
});