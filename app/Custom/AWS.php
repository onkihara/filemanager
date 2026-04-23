<?php
namespace App\Custom;

use Illuminate\Http\Request;
use App\Exceptions\AuthorizationException;

class AWS
{

    public function createSignatureFromRequest(Request $request)
    {
        $auth = $this->authHeader($request->header('authorization'));
        $headers = [
            'credential' => $auth['AWS4-HMAC-SHA256 Credential'],
            'signedheaders' => $auth['SignedHeaders'],
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query(),
            'hashed_content' => hash('sha256',$request->getContent()),
            'date' => $request->header('x-amz-date')
        ];
        $headers = array_merge($this->getSignedHeaders($request,$headers['signedheaders']),$headers);
        return $this->createSignature($headers);
    }

    public function createSignature(array $headers) : string
    {
        $canonical_request = $this->canonicalRequest($headers);
        $string_to_sign = $this->stringToSign($headers, $canonical_request);
        $pattern = "/.+\/(.+)\\/(.+)\/s3\/aws4_request/";
        preg_match($pattern, $headers['credential'], $matches);
        // calculate signature
        $dateKey = hash_hmac('sha256', $matches[1], 'AWS4' . config('auth.s3.secret'), true);
        $dateRegionKey = hash_hmac('sha256', $matches[2], $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signingKey);
        return $signature;
    }



    private function canonicalRequest(array $headers)
    {
        $cr = strtoupper($headers['method'])."\n";
        $cr .= '/'.$headers['path']."\n";
        $cr .= $this->sortAndEncodeQueryString($headers['query'])."\n";
        $cr .= $this->canonicalHeaders($headers, $headers['signedheaders'])."\n";
        $cr .= "\n";
        $cr .= $this->signedHeaders($headers['signedheaders'])."\n";
        $cr .= $headers['hashed_content'];
        return $cr;
    }


    private function stringToSign(array $headers, string $canonical_request)
    {
        $sts = "AWS4-HMAC-SHA256\n";
        $sts .= $headers['date']."\n";
        $scope = explode('/',$headers['credential']);
        $sts .= implode('/',array_slice($scope,1))."\n";
        $sts .= hash('sha256',$canonical_request);
        return $sts;
    }


    private function sortAndEncodeQueryString(array $query)
    {
        $q = [];
        foreach ($query as $key => $value) {
            $q[] = urlencode($key).'='.urlencode($value);
        }
        sort($q);
        return implode('&',$q);
    }


    private function canonicalHeaders(array $headers, string $signed_headers)
    {
        $sh = explode(';',$signed_headers);
        $res = [];
        foreach ($sh as $h) {
            if ( ! isset($headers[$h])) {
                throw new AuthorizationException('S3 signed header '.$h.' missing in request!');
            }
            $res[] = strtolower($h).':'.trim($headers[$h]);
        }
        sort($res);
        return implode("\n",$res);
    }

    private function signedHeaders(string $signed_headers)
    {
        $sh = collect(explode(';',$signed_headers))->map(fn($h) => strtolower($h));
        $sh = $sh->toArray();
        sort($sh);
        return implode(';',$sh);
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



    private function getSignedHeaders(Request $request, string $signedheaders) : array
    {
        $res = [];
        //info($signedheaders);info($request->header());
        foreach (explode(';',$signedheaders) as $sh) {
            $res[$sh] = $request->header($sh);
        }
        return $res;
    }
    


}
