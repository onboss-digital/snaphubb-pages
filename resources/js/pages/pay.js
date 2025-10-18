import 'intl-tel-input/build/css/intlTelInput.css';
import intlTelInput from 'intl-tel-input';

document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.querySelector("input[name='phone']");

    if (phoneInput) {
        const iti = intlTelInput(phoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch("https://ipapi.co/json")
                    .then(function(res) { return res.json(); })
                    .then(function(data) { callback(data.country_code); })
                    .catch(function() { callback("us"); });
            },
            utilsScript: "/build/js/utils.js" // for formatting/validation etc.
        });

        phoneInput.addEventListener('change', function() {
            if (typeof iti.getNumber === 'function') {
                const fullNumber = iti.getNumber();
                if (iti.isValidNumber()) {
                    Livewire.dispatch('updatePhone', { phone: fullNumber });
                }
            }
        });
    }

    // Livewire event listeners
    if (typeof Livewire !== 'undefined') {
        Livewire.on('spots-updated', () => {
            const spotsLeftElement = document.getElementById('spots-left');
            if (spotsLeftElement) {
                spotsLeftElement.classList.add('text-yellow-400');
                setTimeout(() => {
                    spotsLeftElement.classList.remove('text-yellow-400');
                }, 1000);
            }
        });

        Livewire.on('activity-updated', () => {
            const activityElement = document.getElementById('activityCounter');
            if (activityElement) {
                activityElement.classList.add('text-yellow-400');
                setTimeout(() => {
                    activityElement.classList.remove('text-yellow-400');
                }, 500);
            }
        });
    } else {
        console.warn('Livewire not available for event listeners setup.');
    }
});
