<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle;

use Illuminate\Contracts\Events\Dispatcher;
use WeakMap;

/** Register only on upload demand, once per dispatcher; never capture request state. */
final class UploadModelSavedRegistration
{
    /** @var WeakMap<Dispatcher, bool> */
    private WeakMap $dispatchers;

    public function __construct()
    {
        $this->dispatchers = new WeakMap;
    }

    public function ensureRegistered(Dispatcher $events): void
    {
        if (isset($this->dispatchers[$events])) {
            return;
        }
        $events->listen('eloquent.saved: *', static function (string $event, array $payload): void {
            app(UploadModelSaved::class)->handle($event, $payload);
        });
        $this->dispatchers[$events] = true;
    }
}
