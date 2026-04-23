<?php

namespace App\Http\Controllers;

use App;
use Auth;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use App\Exceptions\OauthException;
use GuzzleHttp\Client as Guzzleclient;

class OauthController extends Controller
{
    /**
     * Login with blikk
     */
    public function login()
    {
        return static::oauthLogin();
    }

    /**
     * Register with blikk
     */
    public function register()
    {
        return redirect(config('auth.oauth.server').'/auth/'.App::getLocale().'/register?backurl='.urlencode(url('/oauth/login')));
    }

     /**
     * Login with blikk-Auth
     */
    public static function oauthLogin()
    {
        $query = http_build_query([
            'client_id' => config('auth.oauth.client_id'),
            'redirect_uri' => config('auth.oauth.callback'),
            'response_type' => 'code',
            'scope' => 'user-read',
        ]);
        return redirect(config('auth.oauth.server').'/auth/'.App::getLocale().'/oauth/blikkauthorize?'.$query);
    }

    /**
      * Authorization Callback
      */
     public function oauthAuthorize(Request $request)
     {
        // access denied or some other error
        if ($request->error) {
            throw new OauthException($request->error);
        }

        // new Guzzle-Client
        $http = new Guzzleclient(config('app.guzzle.options'));

        $form_params = [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => config('auth.oauth.client_id'),
                'client_secret' => config('auth.oauth.client_secret'),
                'redirect_uri' => config('auth.oauth.callback'),
                'code' => $request->code,
            ]
        ];

        try {
          $response = $http->post(config('auth.oauth.server').'/auth/oauth/token', $form_params);
          $result = json_decode((string) $response->getBody(), true);
        } catch (\Throwable $exception) {
          // info($exception->getResponse()->getBody());
          throw new OauthException($exception->getMessage());
        }

        // no valid result
        if (empty($result['access_token'])) {
            throw new OauthException('No valid access_token!');
        }

        // now get user information
        $resp = $http->get(config('auth.oauth.server').'/auth/api/user', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $result['token_type'].' '.$result['access_token'],
            ]
        ]);
        $userdata = json_decode((string) $resp->getBody(), true);

        // store access-token in session
        session([
            'accesstoken' => $result['access_token'],
            'tokentype' => $result['token_type'],
            'userdata' => $userdata
        ]);

        // redirect
        return redirect()->intended(route('list'));
     }

     /**
      * Get the stored access-token
      */
     public function getStoredAccessToken()
     {
        if ( ! session()->has('tokentype') || ! session()->has('accesstoken')) {
          return null;
        }
        return ['tokentype' => session('tokentype'), 'accesstoken' => session('accesstoken')];
     }

     /**
      * Logout
      */
     public function logout()
     {

        // deactivate session auth marker
        session()->flush();

        //blikk-logout
        return redirect(config('auth.oauth.server').'/auth/'.App::getLocale().'/logout?backurl='.urlencode(url('/')));
     }


}
