<?php

$proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
foreach ($proxies as $proxy) {
    $parts = explode('/', $proxy);
    $prefix = $parts[1] ?? null;
    $maxPrefix = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
    if (count($parts) > 2 || ! filter_var($parts[0], FILTER_VALIDATE_IP)
        || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix < 1 || (int) $prefix > $maxPrefix))) {
        throw new InvalidArgumentException('TRUSTED_PROXIES must contain explicit proxy IP addresses or restricted CIDRs.');
    }
}

$photoLimit = filter_var(env('REPORT_PHOTO_STORAGE_LIMIT_BYTES', 2147483648), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($photoLimit === false) {
    throw new InvalidArgumentException('REPORT_PHOTO_STORAGE_LIMIT_BYTES must be a non-negative integer byte count.');
}

return [
    'photo_storage_limit_bytes' => $photoLimit,
    'admin_password_hash' => env('ADMIN_REPORT_PASSWORD_HASH'),
    'trusted_proxies' => $proxies,
];
