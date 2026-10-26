<?php
// app/Support/PaymentReceipt.php

namespace App\Support;

/**
 * Validation rule for the receipt / OR number recorded when a fine is
 * marked paid. One place, so the web and mobile (API) controllers agree.
 */
class PaymentReceipt
{
    /**
     * Optional by default; required once config('soms.require_payment_receipt')
     * is on. Letters, digits, spaces and - / . only, which keeps free text
     * (and spreadsheet formula characters) out of exported audit reports.
     */
    public static function rules(): array
    {
        return [
            config('soms.require_payment_receipt') ? 'required' : 'nullable',
            'string',
            'max:60',
            'regex:/^[A-Za-z0-9][A-Za-z0-9 \-\/\.]*$/',
        ];
    }

    /** Trimmed receipt number, or null when none was given. */
    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
