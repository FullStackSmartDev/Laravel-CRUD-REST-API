<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;


class AuthenticateToken
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
        $token = $request->bearerToken();
        $user = User::where('auth_token', $token)->first();

        if(!$user){
            return response()->json([
                'status' => 401,
                'message' => 'Invalid Token!'
            ], 401);
        }
        else{
            $request->user_id = $user->id;
            return $next($request);
        }
    }
}
