<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Events\ImageBundleStored;
use Bjanczak\FilamentFlexFields\Support\Media\Images\GdImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageMedia;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
        $this->markTestSkipped('Image integration needs GD with WebP.');
    }
    MediaCaptureOs::resetRuntimeState();
    Storage::fake('originals');
    Storage::fake('derivatives');
    config()->set('filament-flex-fields.media_capture.require_virus_scan', false);
});

function mediaImageFixture(int $width = 80, int $height = 40): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, (int) ($width / 2), $height - 1, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, (int) ($width / 2), 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

function mediaImageDimensions(string $disk, string $path): array
{
    $size = getimagesizefromstring(Storage::disk($disk)->get($path));

    return [$size[0], $size[1], $size['mime']];
}

it('stores named derivatives on independent disks while preserving exact original bytes', function (): void {
    Event::fake([ImageBundleStored::class]);
    $source = mediaImageFixture();
    $bundle = app(ImageMedia::class)->store(MediaSource::bytes($source, 'source.png'),
        new MediaWrite(disk: 'originals', filename: 'source.png', policy: new MediaPolicy(mimeTypes: ['image/png'])), [
            new ImageConversion('thumb', width: 20, height: 20, fit: ImageFit::Cover, disk: 'derivatives'),
            new ImageConversion('wide', width: 40, format: ImageFormat::Jpeg, quality: 91),
        ]);
    expect(Storage::disk('originals')->get($bundle->original->path))->toBe($source)
        ->and(mediaImageDimensions('derivatives', $bundle->variant('thumb')->path))->toBe([20, 20, 'image/webp'])
        ->and(mediaImageDimensions('originals', $bundle->variant('wide')->path))->toBe([40, 20, 'image/jpeg'])
        ->and(Storage::disk('derivatives')->getVisibility($bundle->variant('thumb')->path))->toBe('private');
    Event::assertDispatchedTimes(ImageBundleStored::class, 1);
    app(ImageMedia::class)->delete($bundle);
    expect(Storage::disk('originals')->allFiles())->toBe([])->and(Storage::disk('derivatives')->allFiles())->toBe([]);
});

it('uses focal cropping and pads with the configured background', function (): void {
    $bundle = app(ImageMedia::class)->store(MediaSource::bytes(mediaImageFixture()), new MediaWrite(disk: 'originals'), [
        new ImageConversion('left', width: 20, height: 20, fit: ImageFit::Cover, format: ImageFormat::Png, focusX: 0),
        new ImageConversion('pad', width: 80, height: 80, fit: ImageFit::Pad, format: ImageFormat::Png, background: '#00ff00'),
    ]);
    $left = imagecreatefromstring(Storage::disk('originals')->get($bundle->variant('left')->path));
    $pad = imagecreatefromstring(Storage::disk('originals')->get($bundle->variant('pad')->path));
    expect(imagecolorat($left, 10, 10) & 0xFFFFFF)->toBe(0xFF0000)
        ->and(imagecolorat($pad, 10, 0) & 0xFFFFFF)->toBe(0x00FF00)
        ->and(mediaImageDimensions('originals', $bundle->variant('pad')->path))->toBe([80, 80, 'image/png']);
});

it('does not shrink a portrait unnecessarily when upscaling is disabled', function (): void {
    $bundle = app(ImageMedia::class)->store(MediaSource::bytes(mediaImageFixture(10, 40)), new MediaWrite(disk: 'originals'), [
        new ImageConversion('contain', width: 40, height: 40),
        new ImageConversion('cover', width: 40, height: 40, fit: ImageFit::Cover),
        new ImageConversion('large', width: 40, height: 160, upscale: true),
    ]);
    expect(mediaImageDimensions('originals', $bundle->variant('contain')->path))->toBe([10, 40, 'image/webp'])
        ->and(mediaImageDimensions('originals', $bundle->variant('cover')->path))->toBe([10, 10, 'image/webp'])
        ->and(mediaImageDimensions('originals', $bundle->variant('large')->path))->toBe([40, 160, 'image/webp']);
});

it('rejects duplicate names and pixel budgets without leaving media behind', function (): void {
    $source = MediaSource::bytes(mediaImageFixture());
    $intent = new MediaWrite(disk: 'originals');
    expect(fn () => app(ImageMedia::class)->store($source, $intent, [new ImageConversion('same'), new ImageConversion('same')]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ImageMedia::class)->store($source, $intent, [new ImageConversion('thumb')], new ImageLimits(sourcePixels: 10)))
        ->toThrow(MediaFailure::class, 'dimensions exceed')
        ->and(Storage::disk('originals')->allFiles())->toBe([]);
});

it('rolls back earlier derivatives and original when a later external encoder fails', function (): void {
    Event::fake([ImageBundleStored::class]);
    Storage::disk('derivatives')->put('unrelated.txt', 'keep');
    app()->instance(ImageEncoder::class, new class implements ImageEncoder
    {
        public function encode(PreparedMedia $original, ImageConversion $conversion, ImageLimits $limits, MediaPolicy $policy): PreparedMedia
        {
            if ($conversion->name === 'second') {
                throw new MediaFailure('external_encoder_failed', 'External encoder failed.');
            }

            return (new GdImageEncoder)->encode($original, $conversion, $limits, $policy);
        }
    });
    expect(fn () => app(ImageMedia::class)->store(MediaSource::bytes(mediaImageFixture()), new MediaWrite(disk: 'originals'), [
        new ImageConversion('first', disk: 'derivatives'), new ImageConversion('second', disk: 'derivatives'),
    ]))->toThrow(MediaFailure::class, 'External encoder failed.')
        ->and(Storage::disk('originals')->allFiles())->toBe([])
        ->and(Storage::disk('derivatives')->allFiles())->toBe(['unrelated.txt']);
    Event::assertNotDispatched(ImageBundleStored::class);
});

it('honors JPEG EXIF orientation and removes metadata from the derivative', function (): void {
    $image = imagecreatefromstring(mediaImageFixture());
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();
    $exif = "Exif\0\0II".pack('vV', 42, 8).pack('v', 1).pack('vvVv', 0x0112, 3, 1, 6)."\0\0".pack('V', 0);
    $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $bundle = app(ImageMedia::class)->store(MediaSource::bytes($jpeg, 'oriented.jpg'), new MediaWrite(disk: 'originals'), [
        new ImageConversion('oriented', format: ImageFormat::Jpeg),
    ]);
    expect(mediaImageDimensions('originals', $bundle->variant('oriented')->path))->toBe([40, 80, 'image/jpeg'])
        ->and(Storage::disk('originals')->get($bundle->variant('oriented')->path))->not->toContain("Exif\0\0");
});

it('rejects GIF flattening unless the author explicitly opts into first frame', function (): void {
    $image = imagecreatefromstring(mediaImageFixture());
    ob_start();
    imagegif($image);
    $gif = ob_get_clean();
    expect(fn () => app(ImageMedia::class)->store(MediaSource::bytes($gif, 'animation.gif'), new MediaWrite(disk: 'originals'), [
        new ImageConversion('frame'),
    ]))->toThrow(MediaFailure::class, 'requires firstFrame');
    $bundle = app(ImageMedia::class)->store(MediaSource::bytes($gif, 'animation.gif'), new MediaWrite(disk: 'originals'), [
        new ImageConversion('frame', firstFrame: true),
    ]);
    expect(mediaImageDimensions('originals', $bundle->variant('frame')->path))->toBe([80, 40, 'image/webp']);
});
