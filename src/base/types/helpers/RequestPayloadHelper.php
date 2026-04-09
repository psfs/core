<?php

namespace PSFS\base\types\helpers;

class RequestPayloadHelper
{
    public static function parseHeaders(array $server): array
    {
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            return is_array($headers) ? $headers : [];
        }

        $headers = [];
        foreach ($server as $headerName => $value) {
            if (preg_match('/HTTP_(.+)/', (string)$headerName, $matches) !== 1) {
                continue;
            }
            $headers[$matches[1]] = $value;
        }

        return $headers;
    }

    /**
     * @return array{cookies:array,upload:array,data:array,query:array}
     */
    public static function hydratePayloadBags(mixed $cookie, mixed $files, mixed $request, mixed $get): array
    {
        return [
            'cookies' => is_array($cookie) ? $cookie : [],
            'upload' => is_array($files) ? $files : [],
            'data' => is_array($request) ? $request : [],
            'query' => is_array($get) ? $get : [],
        ];
    }

    public static function decodeRawBody(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function extractRawBody(array $server): string
    {
        $rawBody = (string)($server['PSFS_RAW_BODY'] ?? '');
        if ($rawBody !== '') {
            return $rawBody;
        }

        return (string)file_get_contents('php://input');
    }
}
