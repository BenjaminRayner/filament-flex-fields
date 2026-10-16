<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\ReceiptCodec;

/** Server-owned receipt set. Form state remains a path, never a client-provided manifest. */
final readonly class UploadManifest
{
    /**
     * @param  array<string, MediaReceipt>  $variants
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public MediaReceipt $original,
        public array $variants = [],
        public string $primary = 'original',
        public array $context = [],
    ) {
        if ($primary !== 'original' && ! isset($variants[$primary])) {
            throw new MediaFailure('manifest_invalid', 'Upload primary conversion is missing.');
        }
    }

    public function withContext(MediaWrite $intent): self
    {
        return new self($this->original, $this->variants, $this->primary,
            [...$this->context, 'kind' => $intent->kind, 'metadata' => $intent->metadata, 'visibility' => $intent->policy?->visibility]);
    }

    public function primaryReceipt(): MediaReceipt
    {
        return $this->primary === 'original' ? $this->original : $this->variants[$this->primary];
    }

    /** @return array<string, MediaReceipt> */
    public function receipts(): array
    {
        return ['original' => $this->original, ...$this->variants];
    }

    /** @return array<string, mixed> */
    public function encode(): array
    {
        return ['version' => 1, 'primary' => $this->primary, 'original' => ReceiptCodec::encode($this->original),
            'variants' => array_map(ReceiptCodec::encode(...), $this->variants), 'context' => $this->context];
    }

    /** @param array<string, mixed> $data */
    public static function decode(array $data): self
    {
        if (($data['version'] ?? null) !== 1 || ! is_string($data['primary'] ?? null)
            || ! is_array($data['original'] ?? null) || ! is_array($data['variants'] ?? null)
            || count($data['variants']) > 64) {
            throw new MediaFailure('manifest_invalid', 'Invalid upload manifest.');
        }
        $variants = [];
        foreach ($data['variants'] as $name => $receipt) {
            if (! is_string($name) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $name) || $name === 'original' || ! is_array($receipt)) {
                throw new MediaFailure('manifest_invalid', 'Invalid upload conversion manifest.');
            }
            $variants[$name] = ReceiptCodec::decode($receipt);
        }

        return new self(ReceiptCodec::decode($data['original']), $variants, $data['primary'],
            is_array($data['context'] ?? null) ? $data['context'] : []);
    }
}
