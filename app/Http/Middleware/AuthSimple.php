<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthSimple
{
    /**
     * Handle an incoming request and validates the session auth
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        //dd(session()->all());
        if (session()->has('accesstoken')) {
            return $next($request);
        }

        return redirect()->route('login');
    }
}
