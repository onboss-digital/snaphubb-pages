import 'intl-tel-input/build/css/intlTelInput.css';
import intlTelInput from 'intl-tel-input';
import Mailcheck from 'mailcheck';

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
    const emailSuggestion = document.createElement('div');
    emailSuggestion.className = 'text-xs text-gray-400 mt-1';
    emailInput.parentElement.appendChild(emailSuggestion);

    if (emailInput) {
        emailInput.addEventListener('blur', () => {
            emailSuggestion.textContent = '';
            Mailcheck.run({
                email: emailInput.value,
                suggested: function(suggestion) {
                    emailSuggestion.innerHTML = `Did you mean <a href="#" class="text-blue-400">${suggestion.full}</a>?`;
                    emailSuggestion.querySelector('a').addEventListener('click', (e) => {
                        e.preventDefault();
                        emailInput.value = suggestion.full;
                        emailSuggestion.textContent = '';
                    });
                },
                empty: function() {
                    // email is empty or no suggestion was found
                }
            });
        });
    }
});
