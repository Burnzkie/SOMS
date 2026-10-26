<?php
// config/soms.php

return [

    /*
    | When true, marking a fine "paid" requires a receipt / OR number. Off by
    | default so existing clients (the mobile app's Mark paid) keep working;
    | the Auditor's discrepancy report flags paid fines that have no receipt
    | either way. Turn on once every client sends one.
    */
    'require_payment_receipt' => (bool) env('SOMS_REQUIRE_PAYMENT_RECEIPT', false),

    /*
    | A flagged offline scan still waiting for an officer's decision after
    | this many days is reported to the Auditor as stale.
    */
    'stale_flagged_scan_days' => (int) env('SOMS_STALE_FLAGGED_SCAN_DAYS', 2),

    /*
    | A waiver reason shorter than this is reported as weak supporting
    | evidence (the form itself only requires 5 characters).
    */
    'min_waiver_reason_length' => (int) env('SOMS_MIN_WAIVER_REASON_LENGTH', 15),
];
