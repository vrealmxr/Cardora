<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class DatasetAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) return $request->expectsJson()
            ? response()->json(['error'=>'Admin login required'],401) : redirect('/admin/login');
        abort_unless($request->user()->is_admin,403);
        $response=$next($request);
        $response->headers->set('Cache-Control','private, no-store');
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Referrer-Policy','no-referrer');
        $response->headers->set('X-Frame-Options','SAMEORIGIN');
        return $response;
    }
}
