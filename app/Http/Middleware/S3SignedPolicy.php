<?php

namespace App\Http\Middleware;

use Closure;
use App\Custom\AWS;
use Illuminate\Http\Request;
use App\Exceptions\AuthorizationException;
use App\Custom\JWT as Token;

class S3SignedPolicy
{



    /**
     * Handle an incoming request and validates s3 policy signature
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->isDirectAccess($request)) {

            $request->merge(["direct-access" => true]);

        } else if ($request->has('policy')) {

            $this->handlePolicy($request);

        } else if ($request->hasHeader('authorization')) {

            $this->handleAuthorization($request);

        } else {
            throw new AuthorizationException('S3 Authorization missing!');
        }

        return $next($request);
    }


    private function isDirectAccess(Request $request)
    {
        if ($request->method() != 'GET') {
            return false;
        }
        if ( ! empty($request->all())) {
            return false;
        }
        return true;
    }


    private function handleAuthorization(Request $request) {
        $aws = new AWS;
        $signature = $aws->createSignatureFromRequest($request);
        $auth = $this->authHeader($request->header('authorization'));
        //info($request->method());info($request->fullUrl());info($request->all());info($signature);info($auth['Signature']);
        if ($signature != $auth['Signature']) {
            throw new AuthorizationException('S3 signature mismatch!');
        }
    }


    private function handlePolicy(Request $request) {
         if ( ! $request->has('x-amz-signature')) {
            throw new AuthorizationException('S3 policy or signature missing!');
        }
        $policy = json_decode(base64_decode($request->input('policy')),true);
        $signature = $this->signV4Policy($policy,$request->input('policy'));
        if ($signature != $request->input('x-amz-signature')) {
            throw new AuthorizationException('S3 signatures don\'t match!');
        }
    }



    private function signV4Policy($policy, $encodedPolicy)
    {
        foreach ($policy['conditions'] as $condition) {
            if (isset($condition['x-amz-credential'])) {
                $credentialCondition = $condition['x-amz-credential'];
            }
        }

        $pattern = "/.+\/(.+)\\/(.+)\/s3\/aws4_request/";
        preg_match($pattern, $credentialCondition ?? '', $matches);

        $dateKey = hash_hmac('sha256', $matches[1], 'AWS4' . config('auth.s3.secret'), true);
        $dateRegionKey = hash_hmac('sha256', $matches[2], $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

        return hash_hmac('sha256', $encodedPolicy, $signingKey);
    }


    private function authHeader($authheader)
    {
        $ah = explode(',',$authheader);
        $res = [];
        foreach ($ah as $h) {
            $hpart = explode('=',$h);
            $res[trim($hpart[0])] = isset($hpart[1]) ? trim($hpart[1]) : '';
        }
        return $res;
    }


}
