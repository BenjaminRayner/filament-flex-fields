<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;
use Bjanczak\FilamentFlexFields\Support\Video\VideoPreviewOptions;

final class MediaConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        if ($type === FieldType::Video) {
            if (array_key_exists('preview_source', $config)) {
                VideoPreviewOptions::source($config['preview_source']);
            }
            if (array_key_exists('preview_thumbnails', $config)) {
                VideoPreviewOptions::thumbnails($config['preview_thumbnails']);
            }
        }
        if (in_array($type, [FieldType::File, FieldType::Image, FieldType::VoiceNote], true)) {
            foreach (['disk', 'directory', 'store_metadata_in', 'media_collection', 'conversion', 'conversions_disk'] as $key) {
                ConfigurationRules::text($config, $key, 1024);
            }
            ConfigurationRules::choice($config, 'storage_driver', ['disk', 'spatie']);
            ConfigurationRules::choice($config, 'visibility', ['public', 'private']);
            foreach (['min_files', 'max_files', 'max_size', 'max_size_kb', 'max_total_size_kb', 'max_image_width', 'max_image_height', 'max_duration'] as $key) {
                ConfigurationRules::integer($config, $key, $key === 'min_files' ? 0 : 1);
            }
            ConfigurationRules::ordered($config, 'min_files', 'max_files');
            ConfigurationRules::identifiers($config, 'accepted_types', null, 256, '~^[a-zA-Z0-9.+_-]+/(?:[a-zA-Z0-9.+_-]+|\*)$~D');
            foreach (['instant_upload', 'compact_header', 'queue_image_conversions', 'use_spatie_media_library'] as $key) {
                ConfigurationRules::boolean($config, $key);
            }
            ConfigurationRules::choice($config, 'upload_layout', ['list', 'grid', 'avatar']);
            ConfigurationRules::integer($config, 'grid_columns', 1, 12);
            if (isset($config['image_conversion_queue'])) {
                $queue = $config['image_conversion_queue'];
                if (! is_array($queue) || ($queue !== [] && array_is_list($queue)) || array_diff(array_keys($queue), ['connection', 'queue', 'tries', 'timeout']) !== []) {
                    ConfigurationRules::fail('image_conversion_queue', 'must contain declared named queue options');
                }
                ConfigurationRules::text($queue, 'connection', 128, '/^[A-Za-z0-9_-]+$/D');
                ConfigurationRules::text($queue, 'queue', 128, '/^[^\x00-\x1f\x7f]+$/D');
                foreach (['tries' => [1, 20], 'timeout' => [1, 3600]] as $key => [$min, $max]) {
                    ConfigurationRules::integer($queue, $key, $min, $max);
                    if (array_key_exists($key, $queue) && ! is_int($queue[$key])) {
                        ConfigurationRules::fail('image_conversion_queue.'.$key, 'requires an integer');
                    }
                }
                if (array_key_exists('queue', $queue) && ! is_string($queue['queue'])) {
                    ConfigurationRules::fail('image_conversion_queue.queue', 'requires text');
                }
                new ConversionQueueOptions(...$queue);
            }
        }
        if ($type === FieldType::VoiceNote) {
            ConfigurationRules::integer($config, 'max_duration', 1, 3600);
            ConfigurationRules::integer($config, 'audio_bits_per_second', 16000, 512000);
            ConfigurationRules::boolean($config, 'confirm_deletion');
            ConfigurationRules::text($config, 'store_waveform_in', 1024);
        }
        if ($type === FieldType::Signature) {
            ConfigurationRules::number($config, 'pen_width', 0.1, 100);
            ConfigurationRules::number($config, 'webp_quality', 0, 1);
            ConfigurationRules::integer($config, 'max_size_kb', 1);
            ConfigurationRules::integer($config, 'min_strokes', 0, 10000);
            foreach (['view_box_width', 'view_box_height'] as $key) {
                ConfigurationRules::integer($config, $key, 1, 32768);
            }
            ConfigurationRules::choice($config, 'download_format', ['png', 'webp', 'svg']);
        }
        if ($type === FieldType::Barcode) {
            foreach (['confirm_scan', 'continuous', 'beep_on_scan', 'auto_submit', 'allow_camera_switch', 'store_detected_format', 'pause_when_hidden', 'allow_manual_input', 'validate_checksum'] as $key) {
                ConfigurationRules::boolean($config, $key);
            }
            ConfigurationRules::choice($config, 'camera_facing', ['user', 'environment']);
            ConfigurationRules::integer($config, 'scan_interval', 16, 60000);
            ConfigurationRules::integer($config, 'scan_delay', 0, 60000);
            ConfigurationRules::integer($config, 'decode_fps', 1, 60);
            ConfigurationRules::identifiers($config, 'formats', null, 100);
        }
    }
}
