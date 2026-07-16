<?php

use Illuminate\Http\Request;

$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', '')),
)));

return [
    // Keep empty by default. Only add fixed reverse-proxy/CIDR addresses.
    'proxies' => $trustedProxies ?: null,

    // Laravel's standard forwarded headers. Nginx + Cloudflare can instead
    // normalize CF-Connecting-IP into REMOTE_ADDR and leave this unset.
    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX,
];
