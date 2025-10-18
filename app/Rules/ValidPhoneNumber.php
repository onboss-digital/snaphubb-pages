<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\PhoneNumberUtil;

class ValidPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumber = $phoneUtil->parse($value, null);
            if (!$phoneUtil->isValidNumber($phoneNumber)) {
                $fail('validation.phone_invalid')->translate();
            }
        } catch (\libphonenumber\NumberParseException $e) {
            $fail('validation.phone_invalid')->translate();
        }
    }
}