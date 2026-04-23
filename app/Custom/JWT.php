<?php
namespace App\Custom;

use Firebase\JWT\JWT as FirebaseJWT;

class JWT
{

    
    /**
     * Returns Token-String from Header or Query-Params or null if none found
     *
     * @param ServerRequest  $request PSR-7 request
     * @return string|null
     */
    static public function retreiveToken($request)
    {
        // Bearer-Token in Header?
        $token = @$request->header('Authorization');
        if ( ! empty($token) && strpos($token, 'Bearer') === 0) {
            $token = explode(' ',$token);
            return $token[1] ?? null;
        }

        // token as url-params
        if (isset($request['token'])) {
            return $request['token'];
        }
        if (isset($request['jwt'])) {
            return $request['jwt'];
        }

        return null;
    }



    /**
     * Decodes the token
     *
     * @param string $token
     * @return 
     */
    static public function decode($token)
    {
        FirebaseJWT::$leeway = config('auth.jwt_leeway');
        return FirebaseJWT::decode($token,config('auth.api.v1.secret'),[config('auth.api.v1.algo')]);
    }




    /**
     * Retreives and decodes the token
     *
     * @param ServerRequest  $request PSR-7 request
     * @return 
     */
    static public function process($request)
    {
        $token = static::retreiveToken($request);
        return static::decode($token);
    }

}
