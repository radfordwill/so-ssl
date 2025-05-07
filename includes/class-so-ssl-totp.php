<?php
/**
 * Time-based One-Time Password (TOTP) Implementation
 *
 * This is a simplified implementation of RFC 6238 TOTP Authentication
 * for use with the So SSL plugin's two-factor authentication feature.
 */


/**
 * Class to handle Two-Factor Authentication
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

class TOTP {
    /**
     * Generate a secret key
     *
     * @param int $length The length of the secret key
     * @return string The secret key
     */
    public static function generateSecret($length = 16) {
        $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $base32_chars[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Verify a TOTP code
     *
     * @param string $secret The secret key
     * @param string $code The code to verify
     * @param int $window The time window for verification (±30 seconds by default)
     * @return bool Whether the code is valid
     */
    public static function verifyCode($secret, $code, $window = 1) {
        // Allow for a bit of time drift
        $timestamp = floor(time() / 30);

        // Check current timestamp and adjacent windows based on window size
        for ($i = -$window; $i <= $window; $i++) {
            $expected_code = self::generateCode($secret, $timestamp + $i);
            if (self::timingSafeEquals($expected_code, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code
     *
     * @param string $secret The secret key
     * @param int|null $timestamp The timestamp to use (current time if null)
     * @return string The generated code
     */
    public static function generateCode($secret, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = floor(time() / 30);
        }

        // Decode the base32 secret
        $secret = self::base32Decode($secret);

        // Pack the timestamp as a binary string
        $time = pack('N*', 0, $timestamp);

        // Generate HMAC-SHA1 hash
        $hash = hash_hmac('sha1', $time, $secret, true);

        // Extract 4 bytes from the hash based on the last byte offset
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        // Generate 6-digit code
        return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a base32 string
     *
     * @param string $base32 The base32 string
     * @return string The decoded string
     */
    private static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32_lookup = array_flip(str_split($base32_chars));

        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($base32) as $char) {
            if (!isset($base32_lookup[$char])) {
                continue;
            }

            $buffer <<= 5;
            $buffer |= $base32_lookup[$char];
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Timing-safe string comparison
     * This prevents timing attacks when verifying codes
     *
     * @param string $expected The expected string
     * @param string $actual The actual string
     * @return bool Whether the strings are equal
     */
    private static function timingSafeEquals($expected, $actual) {
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }

        // Fallback for older PHP versions
        if (strlen($expected) !== strlen($actual)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($expected); $i++) {
            $result |= ord($expected[$i]) ^ ord($actual[$i]);
        }

        return $result === 0;
    }
}
