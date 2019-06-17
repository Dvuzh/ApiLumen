<?php

namespace App\Http\Middleware;

use Closure;
use App\ResponseHelper;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;

class IsAuthorized
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = null;
        if ($request->has("user_id")) {
            $user = User::where('user_id', $request->user_id)->first();
        }

        if (!$user) {
            return ResponseHelper::userDoesNotExist();
        }

        Bugsnag::registerCallback(function ($report) use ($user) {
            $report->setUser([
                'id' => $user->user_id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email
            ]);
        });

        return $next($request);
    }
}
