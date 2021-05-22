<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Log;
use Closure;

class IpMiddleware{
    public function handle($request, Closure $next)
    {
        Log::info('['.$request->ip().']:'.config('allowed_ips'));
        if (!in_array($request->ip(),config('allowed_ips')) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}


