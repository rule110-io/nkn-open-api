<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Log;


public function handle($request, Closure $next)
{
    Log::channel('accessRoutes')->info('['.$request->ip().']:'.$request->fullUrl());

    return $next($request);
};



