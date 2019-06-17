<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use App\ResponseHelper;
use App\User;

// use \Firebase\JWT\JWT;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use PHPUnit\Framework\MockObject\Stub\Exception;
use Illuminate\Support\Facades\Storage;

class Authenticate
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (!is_null($request->header('authorization'))) {
            $header = $request->header('authorization');
            $arrayHeader = $this->getKidUserId($header);
            if ($arrayHeader) {
                $kid = $arrayHeader["kid"];
                $userId = $arrayHeader["user"]["sub"];

                if (!file_exists(storage_path('app/public_key.txt'))) {
                    self::getKey();
                }
                $keys = json_decode(Storage::get('public_key.txt'), true);

                if (array_has($keys, "keys")) {
                    foreach ($keys["keys"] as $key) {
                        if ($key["kid"] == $kid) {
                            try {
                                $publicKey =  JWK::parseKey($key);
                                $verifyToken = JWT::decode($header, $publicKey, array($key["alg"]));
                            } catch (\Exception $exc) {
                                return response()->json(['error' => 'Unauthenticated.'], 401);
                            };
                            break;
                        }
                    }

                    $request->request->add(['user_id' => $userId]);

                    return $next($request);
                }
            }
        } else {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
    }

    private function getKey()
    {
        $region = env('AWS_REGION');
        $userpool_id = env('AWS_USERPOOL_ID');
        $keys_url = 'https://cognito-idp.' . $region . '.amazonaws.com/' . $userpool_id . '/.well-known/jwks.json';
        Storage::disk('local')->put('public_key.txt', file_get_contents($keys_url));
        return true;
    }

    private function getKidUserId($header)
    {
        $arrayHeader = explode('.', $header);
        if (count($arrayHeader) != 3) {
            return false;
        }
        $kid = json_decode(JWT::urlsafeB64Decode($arrayHeader[0]), true)["kid"];
        $userId = json_decode(JWT::urlsafeB64Decode($arrayHeader[1]), true);
        $result = array();
        $result["kid"] = $kid;
        $result["user"] = $userId;
        return $result;
    }
}
