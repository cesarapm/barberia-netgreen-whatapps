<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Message Quota
    |--------------------------------------------------------------------------
    |
    | monthly_quota: 0 or less means unlimited.
    | warning_percent: threshold (1-99) to notify low quota.
    |
    */
    'monthly_quota' => (int) env('MESSAGE_MONTHLY_QUOTA', 1000),
    'warning_percent' => (int) env('MESSAGE_QUOTA_WARNING_PERCENT', 80),
];
