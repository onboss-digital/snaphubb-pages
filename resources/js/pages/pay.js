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
            utilsScript: "/build/js/utils.js"
        });

        phoneInput.addEventListener('change', function() {
            if (typeof iti.getNumber === 'function' && iti.isValidNumber()) {
                const fullNumber = iti.getNumber();
                Livewire.dispatch('updatePhone', { phone: fullNumber });
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
