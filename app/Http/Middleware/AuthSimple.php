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
        if (session()->has('accesstoken')) {
            return $next($request);
        }

        // store request params
        $request->session()->put('request',$request->all());

        return redirect()->route('login');
    }
}
