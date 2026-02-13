<?php

namespace App\Services;


class WordPressPasswordHasher
{
    private string $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Check if a stored hash is a WordPress hash (not native Laravel bcrypt).
     */
    public static function isWordPressHash(string $hash): bool
    {
        // Phpass portable hash
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return true;
        }

        // WP-prefixed bcrypt
        if (str_starts_with($hash, '$wp$')) {
            return true;
        }

        return false;
    }

    /**
     * Verify a plaintext password against a WordPress hash.
     */
    public function check(string $password, string $hash): bool
    {
        // WP-prefixed bcrypt (WordPress 6.8+): uses SHA-384 pre-hashing before bcrypt.
        // The $wp prefix distinguishes these from vanilla bcrypt hashes.
        // See: https://make.wordpress.org/core/2025/02/17/wordpress-6-8-will-use-bcrypt-for-password-hashing/
        if (str_starts_with($hash, '$wp$')) {
            $bcryptHash = substr($hash, 3); // Remove '$wp' prefix → '$2y$12$...'
            return password_verify(hash('sha384', $password), $bcryptHash);
        }

        // Phpass portable hash ($P$ or $H$)
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return $this->checkPhpass($password, $hash);
        }

        return false;
    }

    /**
     * Verify a password against a phpass portable hash.
     *
     * Phpass format: $P$<iteration_char><22_char_salt><hash>
     * - Byte 3: iteration count character (maps to 2^N iterations)
     * - Bytes 4-11: 8-character salt
     * - Remaining: base64-encoded MD5 hash
     */
    private function checkPhpass(string $password, string $storedHash): bool
    {
        if (strlen($storedHash) !== 34) {
            return false;
        }

        $countLog2 = strpos($this->itoa64, $storedHash[3]);
        if ($countLog2 < 7 || $countLog2 > 30) {
            return false;
        }

        $count = 1 << $countLog2;
        $salt = substr($storedHash, 4, 8);

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($storedHash, 0, 12);
        $output .= $this->encode64($hash, 16);

        return hash_equals($storedHash, $output);
    }

    /**
     * Encode binary data to phpass's custom base64 format.
     */
    private function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= $this->itoa64[$value & 0x3f];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $this->itoa64[($value >> 6) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $this->itoa64[($value >> 12) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            $output .= $this->itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
