<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

/** Artifact semantics are explicit; a runtime must not infer them from a URL. */
enum ArtifactKind: string
{
    case Module = 'module';
    case Stylesheet = 'stylesheet';
    case Script = 'script';
    case Worker = 'worker';
    case Wasm = 'wasm';
    case Font = 'font';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Data = 'data';
    case Binary = 'binary';

    /** @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Module, self::Worker => ['js', 'mjs'],
            self::Script => ['js'],
            self::Stylesheet => ['css'],
            self::Wasm => ['wasm'],
            self::Font => ['woff', 'woff2', 'ttf', 'otf'],
            self::Image => ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'svg'],
            self::Audio => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
            self::Video => ['mp4', 'webm', 'mov'],
            self::Data => ['json'],
            self::Binary => ['bin', 'onnx'],
        };
    }
}
