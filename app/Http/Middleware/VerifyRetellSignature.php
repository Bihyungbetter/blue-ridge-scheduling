<?php

namespace App\Http\Middleware;

use App\Retell\RetellSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any /retell/* request that wasn't signed by Retell.
 */
class VerifyRetellSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.retell.api_key');

        if (blank($apiKey)) {
            Log::error('RETELL_API_KEY is not set; rejecting Retell request.');

            return response()->json(['error' => 'Retell integration is not configured.'], 503);
        }

        $signature = (string) $request->header(RetellSignature::HEADER, '');

        if (! RetellSignature::verify($request->getContent(), $apiKey, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
