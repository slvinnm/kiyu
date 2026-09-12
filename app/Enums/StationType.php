<?php

namespace App\Enums;

enum StationType: string
{
    case REGISTRATION = 'registration';
    case NURSE = 'nurse';
    case DOCTOR = 'doctor';
    case LABORATORY = 'laboratory';
    case PHARMACY = 'pharmacy';
    case BILLING = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::REGISTRATION => 'Registration Desk',
            self::NURSE => 'Nurse Station',
            self::DOCTOR => 'Doctor Station',
            self::LABORATORY => 'Laboratory',
            self::PHARMACY => 'Pharmacy',
            self::BILLING => 'Billing / Cashier',
        };
    }
}
