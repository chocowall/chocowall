<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Http\Response;

class BasicAuthenticate
{

    /**
     * @var AuthFactory
     */
    protected AuthFactory $auth;

    /**
     * @param AuthFactory $auth
     */
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return Application|ResponseFactory|Response|mixed|void
     */
    public function handle(Request $request, Closure $next)
    {

        if (!isset($_SERVER['PHP_AUTH_USER'])) {

            header('WWW-Authenticate: Basic realm="My Realm"');
            header('HTTP/1.0 401 Unauthorized');
            exit;

        } else {

            $credentials = [
                'samaccountname' => $_SERVER['PHP_AUTH_USER'],
                'password' => $_SERVER['PHP_AUTH_PW']
            ];

            if (!Auth::attempt($credentials)) {
                return response("Unauthorized: Access is denied due to invalid credentials.", 401);
            }

            return $next($request);

        }
    }

}
