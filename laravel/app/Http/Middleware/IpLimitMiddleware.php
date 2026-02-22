<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 許可するIPアドレスのリスト（localhostとWordPressのIP）
        $allowedIps = [
            '127.0.0.1',
            '::1',
            '***.***.***.***', // WordPressが動いているマシンのIP
        ];

        if (!in_array($request->ip(), $allowedIps)) {
            return response()->json(['error' => 'Unauthorized IP.'], 403);
        }

        return $next($request);
    }
}
