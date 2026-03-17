<?php

namespace PSFS\base\types\traits\Helper;

trait AuthCryptoTrait
{
    private static function isPasswordHash(string $hash): bool
    {
        $info = password_get_info($hash);
        return is_array($info) && array_key_exists('algo', $info) && 0 !== ($info['algo'] ?? 0);
    }

    private static function secureEncrypt(string $data, string $key): false|string
    {
        $ivLen = openssl_cipher_iv_length(self::CRYPTO_CIPHER);
        if (false === $ivLen || $ivLen < 1) {
            return false;
        }
        try {
            $iv = random_bytes($ivLen);
        } catch (\Exception) {
            return false;
        }
        $tag = '';
        $encrypted = openssl_encrypt(
            $data,
            self::CRYPTO_CIPHER,
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::CRYPTO_TAG_LENGTH
        );
        if (false === $encrypted) {
            return false;
        }
        $payload = json_encode([
            'iv' => self::toBase64Url($iv),
            'tag' => self::toBase64Url($tag),
            'data' => self::toBase64Url($encrypted),
        ]);
        if (false === $payload) {
            return false;
        }

        return self::toBase64Url($payload);
    }

    private static function secureDecrypt(string $payload, string $key): false|string
    {
        $decodedPayload = self::fromBase64Url($payload);
        if (false === $decodedPayload) {
            return false;
        }
        $json = json_decode($decodedPayload, true);
        if (!is_array($json) || !isset($json['iv'], $json['tag'], $json['data'])) {
            return false;
        }
        $iv = self::fromBase64Url((string)$json['iv']);
        $tag = self::fromBase64Url((string)$json['tag']);
        $encrypted = self::fromBase64Url((string)$json['data']);
        if (false === $iv || false === $tag || false === $encrypted) {
            return false;
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CRYPTO_CIPHER,
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return false === $decrypted ? false : $decrypted;
    }

    private static function legacyEncrypt(string $data, string $key): string
    {
        $data = base64_encode($data);
        $encrypted_data = '';
        for ($i = 0, $j = 0, $iMax = strlen($data); $i < $iMax; $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $encrypted_data .= $data[$i] ^ $key[$j];
        }
        return base64_encode($encrypted_data);
    }

    private static function legacyDecrypt(string $encrypted_data, string $key): false|string
    {
        $encrypted_data = base64_decode($encrypted_data);
        $data = '';
        for ($i = 0, $j = 0, $iMax = strlen((string)$encrypted_data); $i < $iMax; $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $data .= $encrypted_data[$i] ^ $key[$j];
        }
        return base64_decode($data);
    }

    private static function toBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function fromBase64Url(string $data): false|string
    {
        if ('' === $data) {
            return false;
        }
        $encoded = strtr($data, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($encoded);
    }

    private static function isJson(string $string): bool
    {
        json_decode($string, true);
        return JSON_ERROR_NONE === json_last_error();
    }
}
