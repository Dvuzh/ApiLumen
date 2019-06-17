<?php

namespace App\Http\Middleware;

use Closure;

class HtmlTagMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $requestArray = $request->all();
        if ($requestArray) {
            array_walk_recursive($requestArray, function (&$item) {
                $item = htmlspecialchars($item);
            });
            $request->merge($requestArray);
        }

        return $next($request);
    }
}
