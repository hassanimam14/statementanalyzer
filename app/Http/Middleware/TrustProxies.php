<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware; // Laravel 8+
class TrustProxies extends Middleware
{
    /** @var array|string|null */
    protected $proxies = '*'; // trust the tunnel

    /** @var int */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
