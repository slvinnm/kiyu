<?php

namespace App\Enums;

enum StationType: string
{
    case Registration = 'REGISTRATION';
    case Nurse = 'NURSE';
    case Doctor = 'DOCTOR';
    case Laboratory = 'LABORATORY';
    case Pharmacy = 'PHARMACY';
    case Billing = 'BILLING';
}
