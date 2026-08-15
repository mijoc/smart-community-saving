<?php

return [
    'currency'        => env('VSLA_DEFAULT_CURRENCY', 'UGX'),
    'late_fee_pct'    => (float) env('VSLA_DEFAULT_LATE_FEE_PCT', 5),
    'grace_days'      => (int) env('VSLA_DEFAULT_GRACE_DAYS', 2),

    'frequencies' => [
        'weekly'      => 'Weekly',
        'fortnightly' => 'Fortnightly',
        'monthly'     => 'Monthly',
        'quarterly'   => 'Quarterly',
    ],

    'positions' => [
        'chairperson' => 'Chairperson',
        'secretary'   => 'Secretary',
        'treasurer'   => 'Treasurer',
        'member'      => 'Member',
    ],

    'roles' => [
        'super_admin' => 'Super Admin',
        'group_admin' => 'Group Admin',
        'treasurer'   => 'Treasurer',
        'secretary'   => 'Secretary',
        'member'      => 'Member',
    ],

    'loan_default_days'   => (int) env('VSLA_LOAN_DEFAULT_DAYS',   90),
    'loan_write_off_days' => (int) env('VSLA_LOAN_WRITE_OFF_DAYS', 180),

    'rule_types' => [
        'numeric' => 'Numeric',
        'percent' => 'Percent',
        'days'    => 'Days',
        'string'  => 'String',
        'boolean' => 'Boolean',
    ],
];
