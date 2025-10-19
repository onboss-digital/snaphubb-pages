import intlTelInput from 'intl-tel-input';

document.addEventListener('livewire:init', () => {
    let iti = null;
    const phoneInput = document.querySelector("input[name='phone']");
    let pixPollingInterval = null;

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
});
