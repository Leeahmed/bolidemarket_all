<?php

namespace App\Services;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Throwable;

class PhoneNumbers
{
    public function normalize(string $phone, ?string $country): ?string
    {
        try {
            if (! preg_match('/^[+0-9().\s-]+$/', $phone)) {
                return null;
            }
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse($phone, $country);
            if ($number->hasExtension() || ! $util->isValidNumber($number) || ($country && ! $util->isValidNumberForRegion($number, $country))) {
                return null;
            }

            return $util->format($number, PhoneNumberFormat::E164);
        } catch (Throwable) {
            return null;
        }
    }
}
