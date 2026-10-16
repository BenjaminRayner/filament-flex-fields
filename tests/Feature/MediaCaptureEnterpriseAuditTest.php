<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantDiskResolver;
use Bjanczak\FilamentFlexFields\Support\Media\SignatureLegalPack;

beforeEach(function () {
    MediaCaptureOs::resetRuntimeState();
});

it('resolves signed upload url override on flex file upload', function () {
    MediaCaptureOs::registerSignedUploadUrlResolver(
        fn (string $disk, string $path, array $context): string => "https://signed.test/{$disk}/{$path}",
    );

    $field = FlexFileUpload::make('attachment');
    $field->disk('local');

    expect($field->resolveMediaCaptureSignedUrl('uploads/file.pdf'))
        ->toBe('https://signed.test/local/uploads/file.pdf');
});

it('fail-closes virus scan when required without host callback', function () {
    config(['filament-flex-fields.media_capture.require_virus_scan' => true]);
    MediaCaptureOs::bootFromConfig();

    expect(MediaCaptureOs::passesVirusScan('/tmp/upload.pdf'))->toBeFalse()
        ->and(MediaCaptureOs::hasRegisteredVirusScanner())->toBeFalse();
});

it('resolves tenant disk when auto_disk is enabled', function () {
    config([
        'filament-flex-fields.media_capture.tenant.auto_disk' => true,
        'filament-flex-fields.media_capture.tenant.disk' => 'tenant-uploads',
    ]);

    expect(MediaCaptureTenantDiskResolver::resolveDisk('local'))
        ->toBe('tenant-uploads');
});

it('builds legal audit seal metadata with request context', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 320"><path d="M10,10 L90,90" fill="none" stroke="#000" stroke-width="2"/></svg>';

    $seal = SignatureLegalPack::legalAuditSeal($svg);

    expect($seal)->toHaveKeys(['sealed_at', 'signer_id', 'ip_address', 'user_agent', 'document_hash', 'signature_path_count'])
        ->and($seal['signature_path_count'])->toBe(1);
});
