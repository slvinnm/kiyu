<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case RECEPTIONIST = 'receptionist';
    case NURSE = 'nurse';
    case DOCTOR = 'doctor';
    case PHARMACY = 'pharmacy';
    case LAB = 'lab';
    case STAFF = 'staff';
    case PATIENT = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::RECEPTIONIST => 'Receptionist',
            self::NURSE => 'Nurse',
            self::DOCTOR => 'Doctor',
            self::PHARMACY => 'Pharmacist',
            self::LAB => 'Laboratory Technician',
            self::STAFF => 'General Staff',
            self::PATIENT => 'Patient',
        };
    }
}
