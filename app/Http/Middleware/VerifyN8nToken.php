<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the n8n workflow may call the webhook endpoints: a shared secret sent as a Bearer token. */
class VerifyN8nToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.n8n.token');

        // No token configured means the endpoints stay closed rather than open.
        if ($expected === '') {
            return response()->json(['message' => 'Token n8n belum diatur di server.'], 503);
        }

        $given = (string) $request->bearerToken();

        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Tidak diizinkan.'], 401);
        }

        return $next($request);
    }
}
