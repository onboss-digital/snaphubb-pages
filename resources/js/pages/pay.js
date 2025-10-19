import intlTelInput from 'intl-tel-input';

function setupIntlTelInput(selector, livewireEventName) {
    const input = document.querySelector(selector);
    if (!input) {
        return;
    }

    // Check if the instance already exists and destroy it
    if (input.iti) {
        input.iti.destroy();
    }

    const iti = intlTelInput(input, {
        initialCountry: "auto",
        geoIpLookup: function(callback) {
            fetch("https://ipapi.co/json")
                .then(res => res.json())
                .then(data => callback(data.country_code))
                .catch(() => callback("br")); // Default to Brazil on failure
        },
        utilsScript: "/build/js/utils.js",
        nationalMode: true, // Use national formatting
        formatOnDisplay: true, // Format the number on initialization
    });

    // Store the instance on the element itself
    input.iti = iti;

    // Format as user types
    input.addEventListener('input', () => {
        // utilsScript is loaded asynchronously, so we need to check for window.intlTelInputUtils
        if (window.intlTelInputUtils) {
            const currentNumber = iti.getNumber(window.intlTelInputUtils.numberFormat.NATIONAL);
            if (typeof currentNumber === 'string') {
                // To prevent cursor jumping, only set the value if it's different
                if (input.value !== currentNumber) {
                    input.value = currentNumber;
                }
            }
        }
    });

    // Send the full international number to Livewire on change
    input.addEventListener('change', () => {
        if (iti.isValidNumber()) {
            const fullNumber = iti.getNumber(); // Gets E.164 format
            Livewire.dispatch(livewireEventName, { phone: fullNumber });
        }
    });
}

document.addEventListener('livewire:init', () => {
    function initializeAllPhoneInputs() {
        setupIntlTelInput("input[name='phone']", 'updatePhone');
        setupIntlTelInput("input[name='pix_phone']", 'updatePixPhone'); // Assuming a new event for pix phone
    }

    // Initial call
    initializeAllPhoneInputs();

    // Re-initialize on every Livewire update
    Livewire.hook('message.processed', (message, component) => {
        initializeAllPhoneInputs();
    });
});