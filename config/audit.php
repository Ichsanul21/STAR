<?php

return [
    'enabled' => env('AUDIT_ENABLED', true),
    'log_reads' => env('AUDIT_LOG_READS', false),
    'retention_days' => env('AUDIT_RETENTION_DAYS', 180),
];
