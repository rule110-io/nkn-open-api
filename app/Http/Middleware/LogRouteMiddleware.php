<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Log;
use Closure;

class LogRouteMiddleWare{
    public function handle($request, Closure $next)
    {
        Log::channel('accessRoutes')->info('['.$request->ip().']:'.$request->fullUrl());

        return $next($request);
    }
}


