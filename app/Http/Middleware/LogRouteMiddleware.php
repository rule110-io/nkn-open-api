<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Log;


class LogRouteMiddleWare extends Middleware{
    public function handle($request, Closure $next)
    {
        Log::channel('accessRoutes')->info('['.$request->ip().']:'.$request->fullUrl());

        return $next($request);
    }
}


