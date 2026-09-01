<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Support;

final class Totp
{
    public static function secret(): string
    {
        $bytes = random_bytes(20);

        return self::base32Encode($bytes);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
        $slice = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::at($secret, $slice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function at(string $secret, int $slice): string
    {
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $slice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $truncated, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $chunks = str_split($binary, 5);
        $out = '';
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }

    private static function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(str_replace('=', '', $input));
        $binary = '';
        foreach (str_split($input) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = str_split($binary, 8);
        $out = '';
        foreach ($bytes as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
