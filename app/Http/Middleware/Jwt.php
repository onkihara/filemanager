<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\AuthorizationException;
use App\Custom\JWT as Token;

class Jwt
{
    /**
     * Handle an incoming request and validates jwt-token
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = Token::retreiveToken($request);

        // no token error
        if ( ! $token) {
            throw new AuthorizationException('Token not Found!');
        }

        // token not valid
        try {
            $payload = Token::decode($token);
        } catch (\Throwable $exception) {
            throw new AuthorizationException($exception->getMessage());
        }

        return $next($request);
    }
}
