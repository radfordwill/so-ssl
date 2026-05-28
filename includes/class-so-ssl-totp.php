<?php
if (!defined('ABSPATH')) { exit; }

class So_SSL_TOTP {
    public static function base32_secret($length = 16) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[wp_rand(0, strlen($alphabet) - 1)];
        }
        return $secret;
    }

    public static function verify($secret, $code, $window = 1) {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $time_slice = floor(time() / 30);
        for ($i = -absint($window); $i <= absint($window); $i++) {
            if (hash_equals(self::hotp($secret, $time_slice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function provisioning_uri($user_login, $secret, $issuer = 'So SSL') {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $user_login) . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function hotp($secret, $counter) {
        $key = self::base32_decode($secret);
        $bin_counter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin_counter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string)($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32_decode($secret) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) $secret));
        $bits = '';
        $output = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($alphabet, $secret[$i]);
            if ($val === false) { continue; }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $output .= chr(bindec(substr($bits, $i, 8)));
        }
        return $output;
    }
}
