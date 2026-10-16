<?php

declare(strict_types=1);

// Real optional dependencies, disposable SQLite DB, no application data writes.
$translationDirectory = $argv[1] ?? null;
if ($translationDirectory !== null) {
    spl_autoload_register(static function (string $class) use ($translationDirectory): void {
        $prefix = 'Spatie\\Translatable\\';
        if (str_starts_with($class, $prefix)) {
            $path = $translationDirectory.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    });
}
require __DIR__.'/slug-composition.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error.PHP_EOL);
    exit(1);
});

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SlugField;
use Bjanczak\FilamentFlexFields\Support\Slug\SpatieSlugIntegration as Integration;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Spatie\Sluggable\Actions\BuildSelfHealingRouteKeyAction;
use Spatie\Sluggable\Actions\GenerateSlugAction;
use Spatie\Sluggable\Attributes\Sluggable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SluggableServiceProvider;
use Spatie\Sluggable\SlugOptions;
use Spatie\Sluggable\Support\Config;
use Spatie\Translatable\HasTranslations;
use Spatie\Translatable\Translatable;

$app->register(SluggableServiceProvider::class);
DatabaseSchema::create('slug_spatie_probe', function (Blueprint $table): void {
    $table->id();
    $table->string('title')->nullable();
    $table->string('other')->nullable();
    $table->string('slug')->nullable();
    $table->integer('tenant')->default(1);
    $table->boolean('active')->default(true);
    $table->softDeletes();
});
class SlugSpatieProbeRecord extends Model
{
    use HasSlug;

    protected $table = 'slug_spatie_probe';

    protected $guarded = [];

    public $timestamps = false;

    public ?Closure $configure = null;

    public function getSlugOptions(): SlugOptions
    {
        $options = SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug');

        return $this->configure === null ? $options : ($this->configure)($options, $this);
    }

    public function nativeAutomatic(): void
    {
        $this->exists ? $this->generateSlugOnUpdate() : $this->generateSlugOnCreate();
    }
}
class SlugSpatieCustomAction extends GenerateSlugAction
{
    public function slugifySource(string $source, SlugOptions $options): string
    {
        return 'custom-'.parent::slugifySource($source, $options);
    }
}
class SlugSpatieCustomRouteAction extends BuildSelfHealingRouteKeyAction
{
    public function execute(string $slug, int|string|null $identifier, string $separator): string
    {
        return 'route-'.parent::execute($slug, $identifier, $separator);
    }
}
$count = 0;
$check = static function (bool $condition, string $name) use (&$count): void {
    locationCheck($condition, $name);
    $count++;
    echo 'PASS Spatie: '.$name.PHP_EOL;
};
$parity = static function (string $name, SlugSpatieProbeRecord $record, string $source, array $attributes = [], bool $force = false) use ($check): string {
    $before = [$record->getAttributes(), $record->getRawOriginal(), $record->exists];
    $rows = DB::table('slug_spatie_probe')->count();
    $native = clone $record;
    $native->fill($attributes)->setAttribute('title', $source);
    $force ? $native->generateSlug() : $native->nativeAutomatic();
    $actual = Integration::generate($source, $record, 'slug', 'title', $attributes, null, $force);
    $check($actual === ($native->slug ?? ''), $name.' native parity');
    $check($before === [$record->getAttributes(), $record->getRawOriginal(), $record->exists] && $rows === DB::table('slug_spatie_probe')->count(), $name.' original/DB unchanged');

    return $actual;
};
$new = static function (?Closure $policy = null): SlugSpatieProbeRecord {
    $record = new SlugSpatieProbeRecord;
    $record->configure = $policy;

    return $record;
};
$parity('multi-source', $new(fn (SlugOptions $o) => $o->generateSlugsFrom(['title', 'other'])), 'Łódź', ['other' => 'Harbour']);
$parity('language + separator + source limit', $new(fn (SlugOptions $o) => $o->usingLanguage('de')->usingSeparator('_')->slugsShouldBeNoLongerThan(13)), 'Über große Hafenstadt');
DB::table('slug_spatie_probe')->insert([
    ['title' => 'Shared', 'slug' => 'shared', 'tenant' => 1],
    ['title' => 'Shared', 'slug' => 'shared-1', 'tenant' => 1],
    ['title' => 'Elsewhere', 'slug' => 'scoped', 'tenant' => 2],
]);
$check($parity('numeric collision', $new(), 'Shared') === 'shared-2', 'smallest available numeric suffix');
$parity('custom suffix', $new(fn (SlugOptions $o) => $o->usingSuffixGenerator(fn (string $slug, int $i): string => 'v'.($i + 3))), 'Shared');
$parity('suffix on first occurrence', $new(fn (SlugOptions $o) => $o->startSlugSuffixFrom(5)->useSuffixOnFirstOccurrence()), 'Brand new');
$parity('allow duplicates', $new(fn (SlugOptions $o) => $o->allowDuplicateSlugs()), 'Shared');
$scopePolicy = fn (SlugOptions $o, Model $m) => $o->extraScope(fn ($query) => $query->where('tenant', $m->tenant));
$check($parity('form hydrated scope', $new($scopePolicy), 'Scoped', ['tenant' => 1]) === 'scoped', 'other tenant does not collide');
$parity('closure source', $new(fn (SlugOptions $o) => $o->generateSlugsFrom(fn (Model $m): string => $m->title.' '.$m->other)), 'New', ['other' => 'Value']);
$record = SlugSpatieProbeRecord::first();
$check($parity('record identity ignored', $record, 'Shared') === 'shared', 'own existing slug stays available');
$record->configure = fn (SlugOptions $o) => $o->doNotGenerateSlugsOnUpdate();
$parity('update disabled', $record, 'Changed');
$parity('explicit force update', $record, 'Changed', force: true);
$parity('create disabled', $new(fn (SlugOptions $o) => $o->doNotGenerateSlugsOnCreate()), 'Hidden');
$parity('explicit force create', $new(fn (SlugOptions $o) => $o->doNotGenerateSlugsOnCreate()), 'Visible', force: true);
$record->configure = fn (SlugOptions $o) => $o->preventOverwrite();
$parity('prevent overwrite', $record, 'Changed');
$parity('explicit force overwrite', $record, 'Changed', force: true);
$parity('skip condition', $new(fn (SlugOptions $o, Model $m) => $o->skipGenerateWhen(fn (): bool => ! $m->active)), 'Skipped', ['active' => false]);
$skip = Integration::preview('Ignored', $new(fn (SlugOptions $o) => $o->doNotGenerateSlugsOnCreate()), currentSlug: 'form-current');
$check(! $skip->generated && $skip->value === 'form-current' && $skip->skipReason === 'create-disabled', 'disabled policy preserves current form slug');
$record->configure = null;
$record->slug = 'manual-choice';
$parity('native customized slug preserved', $record, 'Changed');
$cached = new class extends SlugSpatieProbeRecord
{
    public ?SlugOptions $cachedOptions = null;

    public function getSlugOptions(): SlugOptions
    {
        return $this->cachedOptions ??= parent::getSlugOptions()->extraScope(fn ($q) => $q->where('tenant', $this->tenant));
    }
};
$cached->tenant = 2;
$cached->getSlugOptions();
// A raw shallow clone retains the original closure binding. Compare to a fresh
// native policy on the hydrated clone, which is the preview's intended state.
$nativeCached = clone $cached;
$nativeCached->cachedOptions = null;
$nativeCached->fill(['tenant' => 1, 'title' => 'Scoped'])->generateSlug();
$check(Integration::generate('Scoped', $cached, sourceField: 'title', formAttributes: ['tenant' => 1]) === $nativeCached->slug && $nativeCached->slug === 'scoped', 'cached scope rebound to disposable current model');
$check($cached->cachedOptions->slugField === 'slug' && $cached->tenant === 2, 'cached model options and state retained');
$app['config']->set('sluggable.actions.'.Config::ACTION_GENERATE_SLUG, SlugSpatieCustomAction::class);
$check($parity('configured generator action', $new(), 'Example') === 'custom-example', 'configured action actually used');
$app['config']->set('sluggable.actions.'.Config::ACTION_GENERATE_SLUG, GenerateSlugAction::class);
$override = new class extends SlugSpatieProbeRecord
{
    protected function generateSlugAction(): GenerateSlugAction
    {
        return new SlugSpatieCustomAction;
    }
};
$parity('model generator override', $override, 'Model override');
$record->configure = fn (SlugOptions $o) => $o->selfHealing('~');
$app['config']->set('sluggable.actions.'.Config::ACTION_BUILD_SELF_HEALING_ROUTE_KEY, SlugSpatieCustomRouteAction::class);
$check(Integration::buildSelfHealingRouteKey($record->slug, $record) === $record->getRouteKey(), 'configured self-healing action native parity');
$app['config']->set('sluggable.actions.'.Config::ACTION_BUILD_SELF_HEALING_ROUTE_KEY, BuildSelfHealingRouteKeyAction::class);
$attributes = new #[Sluggable(from: 'title', to: 'slug', separator: '_', unique: false)] class extends Model {};
$firstOptions = Integration::resolveSlugOptions($attributes, 'slug');
$firstOptions->slugSeparator = '!';
$check(Integration::resolveSlugOptions($attributes, 'slug')->slugSeparator === '_', 'attribute cache is immutable to callers');
locationReject(fn () => Integration::generate('Title', $new(fn (SlugOptions $o) => $o->generateSlugsFrom('slug')), sourceField: 'title'));
$check(true, 'contradictory source/output configuration rejected');

$record = $new($scopePolicy);
$record->tenant = 1;
$field = SlugField::make('slug')->source('title');
$schema = Schema::make($host)->key('spatie-slug-scope')->model($record)->statePath('data')->components([
    FlexTextInput::make('title'), FlexTextInput::make('tenant'), $field,
]);
$host->data = ['title' => 'Scoped', 'tenant' => 1, 'slug' => 'scoped'];
$schema->getFlatComponents(withHidden: true);
$check($field->isSlugValueUnique('scoped'), 'final validation inherits native scope');
$host->data['tenant'] = 2;
$check(! $field->isSlugValueUnique('scoped'), 'final validation sees changed scope field');
$record->configure = fn (SlugOptions $o) => $o->allowDuplicateSlugs();
$check(! $field->shouldEnforceSlugUnique() && $field->isSlugValueUnique('shared'), 'native duplicate policy inherited');
$field->slugUnique();
$check(! $field->isSlugValueUnique('shared'), 'explicit unique configuration overrides duplicate policy');
// Native Unique rule modifiers must stay on the actual mounted record's DB.
$app['config']->set('database.connections.slug_preview_secondary', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
DB::connection('slug_preview_secondary')->getSchemaBuilder()->create('slug_spatie_probe', function (Blueprint $table): void {
    $table->id();
    $table->string('title')->nullable();
    $table->string('slug')->nullable();
    $table->integer('tenant')->default(1);
    $table->boolean('active')->default(true);
});
DB::connection('slug_preview_secondary')->table('slug_spatie_probe')->insert(['title' => 'Secondary', 'slug' => 'secondary-only']);
$record->configure = null;
$record->setConnection('slug_preview_secondary');
$field->slugUniqueParameters(['modifyRuleUsing' => fn ($rule) => $rule->where('active', 1)]);
$check(! $field->isSlugValueUnique('secondary-only') && $field->isSlugValueUnique('shared'), 'native Unique rule stays on actual record connection');
$record->setConnection(null);
$field->slugUniqueParameters([]);
$record->configure = fn (SlugOptions $o, Model $m) => $o->usingSeparator($m->tenant === 1 ? '_' : '-');
$check($field->getSeparator() === '_', 'separator derived from actual record options');
$field->slugSeparator('--');
$check($field->getSeparator() === '--', 'explicit separator overrides model default');

if ($translationDirectory === null) {
    $check(! trait_exists(HasTranslations::class), 'translatable dependency remains optional');
    echo "PASS: $count native Spatie checks; translated cases require the optional dependency path.\n";
    exit;
}
$app->instance(Translatable::class, new Translatable);
DatabaseSchema::create('slug_spatie_translated_probe', function (Blueprint $table): void {
    $table->id();
    $table->json('title')->nullable();
    $table->json('slug')->nullable();
    $table->integer('tenant')->default(1);
});
class SlugSpatieTranslatedRecord extends Model
{
    use HasTranslatableSlug;
    use HasTranslations;

    protected $table = 'slug_spatie_translated_probe';

    protected $guarded = [];

    public $timestamps = false;

    public array $translatable = ['title', 'slug'];

    public ?Closure $configure = null;

    public function getSlugOptions(): SlugOptions
    {
        $options = SlugOptions::createWithLocales(['en', 'pl'])->generateSlugsFrom('title')->saveSlugsTo('slug');

        return $this->configure === null ? $options : ($this->configure)($options, $this);
    }

    public function nativeAutomatic(): void
    {
        $this->exists ? $this->generateSlugOnUpdate() : $this->generateSlugOnCreate();
    }
}
$translatedParity = static function (string $name, SlugSpatieTranslatedRecord $record, string $source, string $locale, bool $force = false) use ($check): string {
    $before = [$record->getAttributes(), $record->getRawOriginal(), $record->getLocale()];
    $rows = DB::table('slug_spatie_translated_probe')->count();
    $native = clone $record;
    $native->setLocale($locale)->setTranslation('title', $locale, $source);
    $force ? $native->generateSlug() : $native->nativeAutomatic();
    $actual = Integration::generate($source, $record, 'slug', 'title', [], $locale, $force);
    $check($actual === $native->getTranslation('slug', $locale, false), $name.' native translated parity');
    $check($before === [$record->getAttributes(), $record->getRawOriginal(), $record->getLocale()] && $rows === DB::table('slug_spatie_translated_probe')->count(), $name.' original/locales/DB unchanged');

    return $actual;
};
$t = new SlugSpatieTranslatedRecord;
$t->setTranslations('title', ['en' => 'Hello harbour', 'pl' => 'Witaj porcie']);
$translatedParity('selected PL locale', $t, 'Łódź i morze', 'pl');
$t->save();
$translatedParity('existing automatic translation changes', $t, 'Nowy port', 'pl');
DB::table('slug_spatie_translated_probe')->insert(['title' => json_encode(['en' => 'Shared']), 'slug' => json_encode(['en' => 'same', 'pl' => 'wspolny']), 'tenant' => 1]);
$check($translatedParity('JSON locale collision', new SlugSpatieTranslatedRecord, 'Wspolny', 'pl') === 'wspolny-1', 'locale-specific suffix');
$check($translatedParity('other locale independent', new SlugSpatieTranslatedRecord, 'Wspolny', 'en') === 'wspolny', 'other locale does not collide');
$t->setTranslation('slug', 'pl', 'manual-pl');
$translatedParity('customized translation preserved', $t, 'Nowy port', 'pl');
$t->syncOriginal();
$t->configure = fn (SlugOptions $o) => $o->preventOverwrite();
$translatedParity('translated prevent overwrite', $t, 'Changed', 'pl');
$translatedParity('translated explicit force', $t, 'Changed', 'pl', true);
$t->configure = fn (SlugOptions $o) => $o->generateSlugsFrom(fn (Model $m, string $locale): string => $m->getTranslation('title', $locale, false).' custom');
$translatedParity('locale-aware source closure', $t, 'Changed', 'pl');
$t->configure = null;
$regional = new SlugSpatieTranslatedRecord;
$regional->setTranslations('title', ['pt_BR' => 'Porto', 'en' => 'Harbour']);
$translatedParity('exact regional locale', $regional, 'São Paulo', 'pt_BR');
$t->configure = fn (SlugOptions $o) => $o->doNotGenerateSlugsOnUpdate();
$translatedParity('translated update disabled', $t, 'Changed', 'pl');
$t->configure = fn (SlugOptions $o) => $o->skipGenerateWhen(fn (): bool => true);
$translatedParity('translated skip condition', $t, 'Changed', 'pl');
$t->configure = null;
$field = SlugField::make('slug')->source('title')->slugSourceLocale('pl');
$schema = Schema::make($host)->key('spatie-translated-uniqueness')->model($t)->statePath('data')->components([$field]);
$host->data = ['title' => $t->getTranslations('title'), 'slug' => 'manual-pl'];
$schema->getFlatComponents(withHidden: true);
$check($field->getRecordSlug() === 'manual-pl', 'record slug reads selected locale without fallback');
$check(! $field->isSlugValueUnique('wspolny') && $field->isSlugValueUnique('same'), 'final validation uses selected JSON locale');
$field->slugSourceLocale('fr');
$check($field->getRecordSlug() === null, 'missing locale never copies a fallback slug');
echo "PASS: $count native Spatie policy/generation/scope/locale/action checks.\n";
