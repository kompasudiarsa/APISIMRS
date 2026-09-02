<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiSignature
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        /*
        |--------------------------------------------------------------------------
        | Ambil Header
        |--------------------------------------------------------------------------
        */

        $token = trim(
            (string) $request->header('X-Token')
        );

        $timestamp = trim(
            (string) $request->header('X-Timestamp')
        );

        $signature = strtolower(
            trim(
                (string) $request->header('X-Signature')
            )
        );


        /*
        |--------------------------------------------------------------------------
        | 1. Validasi Header
        |--------------------------------------------------------------------------
        */

        if (
            $token === '' ||
            $timestamp === '' ||
            $signature === ''
        ) {
            return $this->errorResponse(
                401,
                'Header autentikasi tidak lengkap.',
                [
                    'required_headers' => [
                        'X-Token',
                        'X-Timestamp',
                        'X-Signature',
                    ],
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Validasi Token
        |--------------------------------------------------------------------------
        */

        $validToken = trim(
            (string) config(
                'api_service.client_token'
            )
        );

        if ($validToken === '') {
            return $this->errorResponse(
                500,
                'Konfigurasi token API belum tersedia.'
            );
        }

        if (! hash_equals($validToken, $token)) {
            return $this->errorResponse(
                401,
                'Token tidak valid.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Validasi Timestamp
        |--------------------------------------------------------------------------
        */

        if (! ctype_digit($timestamp)) {
            return $this->errorResponse(
                401,
                'Timestamp harus berupa Unix timestamp dalam detik.'
            );
        }

        $requestTimestamp = (int) $timestamp;
        $serverTimestamp = time();

        $tolerance = (int) config(
            'api_service.timestamp_tolerance',
            300
        );

        if (
            abs(
                $serverTimestamp -
                $requestTimestamp
            ) > $tolerance
        ) {
            return $this->errorResponse(
                401,
                'Request sudah kedaluwarsa.',
                [
                    'server_timestamp' =>
                        $serverTimestamp,

                    'request_timestamp' =>
                        $requestTimestamp,

                    'tolerance_seconds' =>
                        $tolerance,
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Validasi Format Signature
        |--------------------------------------------------------------------------
        */

        if (
            ! preg_match(
                '/^[a-f0-9]{64}$/',
                $signature
            )
        ) {
            return $this->errorResponse(
                401,
                'Format signature tidak valid.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Canonical URI
        |--------------------------------------------------------------------------
        |
        | PENTING:
        |
        | Query string dipertahankan sesuai urutan yang dikirim client.
        |
        | Contoh:
        |
        | /api/service/getborlostoi
        | ?tglAwal=2026-08-01
        | &tglAkhir=2026-08-31
        |
        | Harus sama persis dengan Postman.
        |
        */

        $canonicalUri = $this->canonicalUri(
            $request
        );


        /*
        |--------------------------------------------------------------------------
        | 6. Hash Request Body
        |--------------------------------------------------------------------------
        */

        $rawBody = (string) $request->getContent();

        $bodyHash = hash(
            'sha256',
            $rawBody
        );


        /*
        |--------------------------------------------------------------------------
        | 7. Signature Payload
        |--------------------------------------------------------------------------
        |
        | METHOD
        | REQUEST_URI
        | TIMESTAMP
        | SHA256_BODY
        |
        */

        $signaturePayload = implode(
            "\n",
            [
                strtoupper(
                    $request->method()
                ),

                $canonicalUri,

                $timestamp,

                $bodyHash,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | 8. Secret
        |--------------------------------------------------------------------------
        */

        $secret = trim(
            (string) config(
                'api_service.client_secret'
            )
        );

        if ($secret === '') {
            return $this->errorResponse(
                500,
                'Konfigurasi secret API belum tersedia.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 9. Generate Expected Signature
        |--------------------------------------------------------------------------
        */

        $expectedSignature = hash_hmac(
            'sha256',
            $signaturePayload,
            $secret
        );


        /*
        |--------------------------------------------------------------------------
        | 10. Bandingkan Signature
        |--------------------------------------------------------------------------
        */

        if (
            ! hash_equals(
                $expectedSignature,
                $signature
            )
        ) {

            /*
            |--------------------------------------------------------------------------
            | Debug khusus environment local
            |--------------------------------------------------------------------------
            |
            | Jangan tampilkan secret.
            |
            */

            $debug = [];

            if (app()->environment('local')) {
                $debug = [
                    'method' => strtoupper(
                        $request->method()
                    ),

                    'canonical_uri' =>
                        $canonicalUri,

                    'timestamp' =>
                        $timestamp,

                    'body_hash' =>
                        $bodyHash,

                    'payload' =>
                        $signaturePayload,

                    'received_signature' =>
                        $signature,

                    'expected_signature' =>
                        $expectedSignature,
                ];
            }

            return $this->errorResponse(
                401,
                'Signature tidak valid.',
                $debug
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 11. Replay Attack Protection
        |--------------------------------------------------------------------------
        */

        $replayKey = 'api-replay:' . hash(
            'sha256',
            implode(
                '|',
                [
                    $token,
                    $timestamp,
                    $signature,
                ]
            )
        );

        $isNewRequest = Cache::add(
            $replayKey,
            true,
            $tolerance
        );

        if (! $isNewRequest) {
            return $this->errorResponse(
                409,
                'Request yang sama sudah pernah diproses.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 12. Request Valid
        |--------------------------------------------------------------------------
        */

        return $next($request);
    }


    /**
     * Membentuk URI persis sesuai request client.
     *
     * Query parameter TIDAK di-sort.
     */
    private function canonicalUri(
        Request $request
    ): string {

        $uri = $request->getPathInfo();

        $queryString = $request
            ->server
            ->get('QUERY_STRING', '');

        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        return $uri;
    }


    /**
     * Standard response error API.
     */
    private function errorResponse(
        int $code,
        string $message,
        array $response = []
    ): JsonResponse {

        return response()->json(
            [
                'metadata' => [
                    'code' => $code,
                    'message' => $message,
                ],

                'response' =>
                    ! empty($response)
                        ? $response
                        : null,
            ],
            $code
        );
    }
}