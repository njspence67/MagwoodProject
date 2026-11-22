<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Preserve the API's explicit allowlist and its rejected-preflight behavior. */
class ApiCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowed = $origin !== null && in_array($origin, config('herd.cors_origins', []), true);
        if ($request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            $method = strtoupper($request->headers->get('Access-Control-Request-Method'));
            $headers = array_filter(array_map('trim', explode(',', strtolower($request->headers->get('Access-Control-Request-Headers', '')))));
            if (! $allowed || ! in_array($method, ['GET', 'POST'], true)
                || array_diff($headers, ['accept', 'accept-language', 'content-language', 'content-type'])) {
                return response()->json(['error' => ['code' => 'cors_error', 'message' => 'CORS preflight rejected', 'fields' => []]], 400)
                    ->header('Vary', 'Origin');
            }

            return response('', 200)->withHeaders([
                'Access-Control-Allow-Origin' => $origin, 'Access-Control-Allow-Methods' => 'GET, POST',
                'Access-Control-Allow-Headers' => 'Accept, Accept-Language, Content-Language, Content-Type',
                'Access-Control-Max-Age' => '600', 'Vary' => 'Origin',
            ]);
        }
        $response = $next($request);
        $response->setVary('Origin', false);
        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Expose-Headers', 'Location');
        }

        return $response;
    }
}
