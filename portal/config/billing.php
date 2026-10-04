<?php

// What appears on an invoice. Set these in .env on the server; the bank
// details are what families pay into, so check them twice.
return [
    'company_name'    => env('BILLING_COMPANY_NAME', 'Sutera Care Provider'),
    'company_address' => env('BILLING_COMPANY_ADDRESS', 'Kuala Lumpur, Malaysia'),
    'company_reg'     => env('BILLING_COMPANY_REG', ''),
    'company_email'   => env('BILLING_COMPANY_EMAIL', ''),
    'company_phone'   => env('BILLING_COMPANY_PHONE', ''),

    'bank_name'       => env('BILLING_BANK_NAME', ''),
    'account_name'    => env('BILLING_ACCOUNT_NAME', ''),
    'account_number'  => env('BILLING_ACCOUNT_NUMBER', ''),

    // Days between issuing an invoice and its due date.
    'due_days'        => (int) env('BILLING_DUE_DAYS', 14),
];
