<?php

namespace App\Service\Phone;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class PhoneNumberService
{
    private const DEFAULT_REGION = 'MX';

    public function __construct(
        private readonly PhoneNumberUtil $phoneNumberUtil
    ) {
    }

    /**
     * Normaliza un teléfono para guardar y enviar por WhatsApp.
     *
     * Devuelve:
     * - countryCode: +52, +53, +57, etc.
     * - phone: número nacional sin lada internacional
     * - e164: formato internacional con +
     * - whatsapp: destinatario final para WhatsApp Cloud API sin +
     */
    public function normalize(?string $countryCode, ?string $phone, ?string $defaultRegion = self::DEFAULT_REGION): array
    {
        $rawCountryCode = trim((string) $countryCode);
        $rawPhone = trim((string) $phone);

        if ($rawPhone === '') {
            return [
                'isValid' => false,
                'isPossible' => false,
                'countryCode' => $rawCountryCode !== '' ? $this->normalizeCountryCode($rawCountryCode) : null,
                'phone' => null,
                'e164' => null,
                'whatsapp' => null,
                'regionCode' => null,
                'error' => 'PHONE_EMPTY',
            ];
        }

        $candidate = $this->buildCandidate($rawCountryCode, $rawPhone);

        try {
            $number = $this->phoneNumberUtil->parse($candidate, $defaultRegion ?: self::DEFAULT_REGION);
        } catch (NumberParseException $exception) {
            return [
                'isValid' => false,
                'isPossible' => false,
                'countryCode' => $rawCountryCode !== '' ? $this->normalizeCountryCode($rawCountryCode) : null,
                'phone' => $this->digitsOnly($rawPhone),
                'e164' => null,
                'whatsapp' => null,
                'regionCode' => null,
                'error' => 'PHONE_PARSE_ERROR',
                'message' => $exception->getMessage(),
            ];
        }

        $isPossible = $this->phoneNumberUtil->isPossibleNumber($number);
        $isValid = $this->phoneNumberUtil->isValidNumber($number);
        $regionCode = $this->phoneNumberUtil->getRegionCodeForNumber($number);

        $countryCodeDigits = (string) $number->getCountryCode();
        $nationalNumber = (string) $number->getNationalNumber();
        $countryCodeNormalized = '+' . $countryCodeDigits;
        $e164 = $this->phoneNumberUtil->format($number, PhoneNumberFormat::E164);

        $whatsAppRecipient = $this->toWhatsAppRecipient(
            countryCodeDigits: $countryCodeDigits,
            nationalNumber: $nationalNumber,
            e164: $e164
        );

        return [
            'isValid' => $isValid,
            'isPossible' => $isPossible,
            'countryCode' => $countryCodeNormalized,
            'phone' => $nationalNumber,
            'e164' => $e164,
            'whatsapp' => $whatsAppRecipient,
            'regionCode' => $regionCode,
            'error' => $isValid ? null : 'PHONE_NOT_VALID',
        ];
    }

    public function normalizeForWhatsApp(?string $countryCode, ?string $phone, ?string $defaultRegion = self::DEFAULT_REGION): ?string
    {
        $normalized = $this->normalize($countryCode, $phone, $defaultRegion);

        if (($normalized['whatsapp'] ?? null) === null) {
            return null;
        }

        return (string) $normalized['whatsapp'];
    }

    public function detect(?string $phone, ?string $defaultRegion = self::DEFAULT_REGION): array
    {
        return $this->normalize(null, $phone, $defaultRegion);
    }

    private function buildCandidate(string $countryCode, string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '00')) {
            return '+' . substr($phone, 2);
        }

        $countryCode = $this->normalizeCountryCode($countryCode);

        if ($countryCode !== '') {
            $phoneDigits = $this->digitsOnly($phone);
            $countryDigits = $this->digitsOnly($countryCode);

            if ($countryDigits !== '' && str_starts_with($phoneDigits, $countryDigits)) {
                return '+' . $phoneDigits;
            }

            return $countryCode . $phoneDigits;
        }

        return $phone;
    }

    private function normalizeCountryCode(string $countryCode): string
    {
        $digits = $this->digitsOnly($countryCode);

        if ($digits === '') {
            return '';
        }

        return '+' . $digits;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function toWhatsAppRecipient(string $countryCodeDigits, string $nationalNumber, string $e164): string
    {
        $digits = $this->digitsOnly($e164);

        /*
         * Regla especial México para WhatsApp Cloud API.
         *
         * Para México mantenemos el formato 521 + número nacional.
         * Esta regla NO debe aplicarse a Cuba, Colombia, España, Argentina, etc.
         */
        if ($countryCodeDigits === '52') {
            if (str_starts_with($digits, '521')) {
                return $digits;
            }

            if (str_starts_with($digits, '52')) {
                return '521' . substr($digits, 2);
            }

            return '521' . $nationalNumber;
        }

        return $digits;
    }
}
