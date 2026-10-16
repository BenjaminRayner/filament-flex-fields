<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/** Dual-read legacy encryption; all new writes preserve their original value type. */
final class FlexFieldEncryption
{
    private const PREFIX = 'fff:encrypted:v1:';

    public static function encrypt(mixed $value): string
    {
        return self::PREFIX.Crypt::encryptString(FieldValueCodec::encode($value));
    }

    public static function decrypt(mixed $value): mixed
    {
        return self::inspect($value)['value'];
    }

    /** @return array{format: 'typed'|'legacy_encrypted'|'legacy_plaintext', value: mixed} */
    public static function inspect(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return ['format' => 'legacy_plaintext', 'value' => $value];
        }

        if (str_starts_with($value, self::PREFIX)) {
            return [
                'format' => 'typed',
                'value' => FieldValueCodec::decode(Crypt::decryptString(substr($value, strlen(self::PREFIX)))),
            ];
        }

        if (str_starts_with($value, 'fff:encrypted:')) {
            throw new DecryptException('Unsupported encrypted field value version.');
        }

        try {
            $decrypted = Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            // A damaged Laravel envelope must not silently become plaintext.
            $decoded = base64_decode($value, true);
            $envelope = is_string($decoded) ? json_decode($decoded, true) : null;

            if (is_array($envelope) && isset($envelope['iv'], $envelope['value'])) {
                throw $exception;
            }

            return ['format' => 'legacy_plaintext', 'value' => $value];
        }

        $json = json_decode($decrypted, true);

        // Old scalar ciphertexts are ambiguous; keep historical decoding for migration.
        return ['format' => 'legacy_encrypted', 'value' => json_last_error() === JSON_ERROR_NONE ? $json : $decrypted];
    }

    public static function isEncryptedPayload(mixed $value): bool
    {
        return self::inspect($value)['format'] !== 'legacy_plaintext';
    }
}
