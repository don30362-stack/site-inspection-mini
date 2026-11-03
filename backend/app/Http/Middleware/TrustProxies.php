<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        // No wildcard, host-based auto-trust, or forwarded host/protocol trust.
        $request->setTrustedProxies(config('inspection.trusted_proxies', []), Request::HEADER_X_FORWARDED_FOR);
    }
}
