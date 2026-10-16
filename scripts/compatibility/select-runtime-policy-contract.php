<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CatalogSelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndex;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndexBuilder;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogResolver;
use Bjanczak\FilamentFlexFields\Support\Select\Catalog\PickerCatalogState;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionIdentityList;
use Bjanczak\FilamentFlexFields\Support\Select\Data\StringSuggestions;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Composer\InstalledVersions;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
Container::getInstance()->instance('config', new Repository(['filament-flex-fields' => []]));
Container::getInstance()->instance('translator', new Translator(new ArrayLoader, 'en'));
Container::getInstance()->instance('request', Request::create('/'));
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function invalid(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Malformed runtime input was accepted.');
}
class PolicySelect extends SelectField
{
    protected function renderRichOptionLabel(array $option, string $layout = 'list'): string
    {
        if ($option['label'] === 'throw') {
            throw new RuntimeException('Deliberate render failure');
        }

        return 'rich:'.$layout.':'.$option['label'];
    }
}
class PolicyCatalogSelect extends CatalogSelectField
{
    public bool $payloadDisabled = false;

    protected function catalogOptions(): array
    {
        return ['PL' => ['label' => 'Poland', 'description' => '+48', 'image' => '/pl.svg', 'disabled' => $this->payloadDisabled]];
    }

    protected function renderRichOptionLabel(array $option, string $layout = 'list'): string
    {
        return 'rich:'.$layout.':'.$option['label'];
    }
}
$catalogSelect = PolicyCatalogSelect::make('country')->optionDescriptionPosition('trailing');
$catalogRecord = $catalogSelect->getOptionsForJs()[0];
ensure($catalogRecord['fffCatalogRender'] && $catalogRecord['label'] === 'Poland' && $catalogRecord['description'] === '+48', 'Compact catalog discarded primary or trailing text');
ensure($catalogRecord['descriptionPosition'] === 'trailing', 'Trailing position missing from catalog transport');
$enabledKey = $catalogSelect->getSelectOptionsRegistryKey();
$catalogSelect->payloadDisabled = true;
ensure($catalogSelect->getSelectOptionsRegistryKey() !== $enabledKey && $catalogSelect->getOptionsForJs()[0]['isDisabled'], 'Explicit disabled catalog metadata reused enabled transport');
$catalogSelect->payloadDisabled = false;
$trailingKey = $catalogSelect->getSelectOptionsRegistryKey();
$catalogSelect->optionDescriptionPosition('below');
ensure($catalogSelect->getSelectOptionsRegistryKey() !== $trailingKey, 'Description position retained stale transport');
$richKey = $catalogSelect->getSelectOptionsRegistryKey();
$catalogSelect->richOptions(false);
ensure($catalogSelect->getSelectOptionsRegistryKey() !== $richKey && ! isset($catalogSelect->getOptionsForJs()[0]['fffCatalogRender']), 'Plain rendering retained compact rich transport');
invalid(fn () => $catalogSelect->optionDescriptionPosition('right')->getOptionDescriptionPosition());
$catalogSelect->optionDescriptionPosition('trailing')->richOptions()->disableOptionWhen(fn ($value): bool => $value === 'PL');
ensure($catalogSelect->getOptionsForJs()[0]['isDisabled'], 'Compact catalog lost disabled option policy');
$customRecord = $catalogSelect->transformRichOptionsForJs(['PL' => ['label' => 'Poland', 'badge' => 'Featured']])[0];
ensure(! isset($customRecord['fffCatalogRender']) && str_starts_with($customRecord['label'], 'rich:'), 'Unsupported compact metadata bypassed the full Select renderer');
$customLabel = 'First';
$customCatalog = PolicyCatalogSelect::make('custom')->transformOptionsForJsUsing(function () use (&$customLabel): array {
    return [['value' => 'PL', 'label' => $customLabel]];
});
$customKey = $customCatalog->getSelectOptionsRegistryKey();
$customLabel = 'Changed';
$customCatalog->getSelectOptionsRegistryKey();
ensure(request()->attributes->get('fff.select.catalogs')[$customKey][0]['label'] === 'Changed', 'Custom transformer was frozen by catalog memoization');
class PolicyCatalogResolver extends IconCatalogResolver
{
    public function __construct() {}

    public function indexFor(array $catalog, array $whitelist = [], array $exclude = [], ?int $limitPerSet = null): IconCatalogIndex
    {
        return (new IconCatalogIndexBuilder)->build($catalog, $whitelist, $exclude, $limitPerSet, static fn (string $name): string => $name);
    }
}
Container::getInstance()->instance(IconCatalogResolver::class, new PolicyCatalogResolver);
class PolicyIconPicker extends IconPickerField
{
    public function getIconCatalog(): array
    {
        return ['fixture' => ['prefix' => 'fx', 'label' => 'Fixture', 'icons' => ['fx-a', 'fx-b', 'fx-c']]];
    }
}
$select = (new PolicySelect('choice'))->options(['a' => 'Alpha']);
ensure(! $select->usesRichOptionHtml(), 'Static string list detected as rich');
$select->optionLayout('grid');
ensure($select->usesRichOptionHtml(), 'Layout mutation retained plain rendering cache');
$select->optionLayout('list')->allowHtml();
ensure($select->usesRichOptionHtml(), 'Native HTML policy retained plain rendering cache');
$select->allowHtml(false);
$rich = true;
$select->richOptions(static function () use (&$rich): bool {
    return $rich;
});
ensure($select->usesRichOptionHtml(), 'Dynamic rich policy true');
$rich = false;
ensure(! $select->usesRichOptionHtml(), 'Dynamic rich policy change was not observed');
$select = (new PolicySelect('choice'))->options(['a' => 'Alpha']);
$transport = $select->transformRichOptionsForJs(['b' => ['label' => 'Beta', 'description' => 'Details']]);
ensure(str_starts_with($transport[0]['label'], 'rich:'), 'AJAX rich option shape was not applied');
ensure(! $select->usesRichOptionHtml(), 'AJAX result poisoned static option rendering');
try {
    $select->transformRichOptionsForJs(['b' => ['label' => 'throw', 'description' => 'Details']]);
    throw new LogicException('Expected rendering failure');
} catch (RuntimeException $error) {
    ensure($error->getMessage() === 'Deliberate render failure', 'Unexpected renderer exception');
}
ensure(! $select->usesRichOptionHtml(), 'Failed render leaked its transformation scope');
invalid(fn () => SearchQueryInput::assert("\xff"));
invalid(fn () => SearchQueryInput::assert(str_repeat('a', 513)));
invalid(fn () => $select->getSearchResultsForJs("\xff"));
invalid(fn () => $select->getSearchResultsPageForJs("\xff"));
invalid(fn () => (new StringSuggestions)->normalize([INF]));
invalid(fn () => (new StringSuggestions)->normalize(["\xff"]));
$providerCalls = 0;
$providerClass = null;
$user = (new UserSelect('user'))->optionModel(static function () use (&$providerCalls, &$providerClass): ?string {
    $providerCalls++;

    return $providerClass;
});
ensure($providerCalls === 0 && $user->hasDynamicSearchResults() && $user->hasDynamicOptions(), 'Model declaration was evaluated early or lost dynamic bindings');
ensure($user->getUserModel() === null, 'Temporarily unavailable model');
$providerClass = Model::class;
ensure($user->getUserModel() === $providerClass, 'Model change was not observed');
invalid(fn () => $user->nameColumn(fn () => [])->getNameColumn());
$tags = new TagsField('tags');
invalid(fn () => $tags->normalizeTagsState(true));
invalid(fn () => $tags->normalizeTagsState(["\xff"]));
invalid(fn () => $tags->maxTags(-1)->getMaxTags());
$cacheState = new PickerCatalogState;
$cacheState->touchLocale('old');
$cacheState->countryNames['old']['PL'] = 'Old';
for ($locale = 0; $locale < 32; $locale++) {
    $cacheState->touchLocale('locale-'.$locale);
}
ensure(! isset($cacheState->countryNames['old']), 'Localized cache retained an evicted locale');
$cacheState->synchronizeTimezoneInstant(60);
$cacheState->timezoneMetadata['en']['UTC'] = ['id' => 'UTC', 'label' => 'UTC', 'offset' => 'UTC+00:00', 'offset_seconds' => 0, 'region' => 'UTC'];
$cacheState->synchronizeTimezoneInstant(119);
ensure($cacheState->timezoneMetadata !== [], 'Timezone cache expired inside one minute');
$cacheState->synchronizeTimezoneInstant(120);
ensure($cacheState->timezoneMetadata === [], 'Timezone cache survived an offset snapshot boundary');
ensure(CurrencyCountries::decimalSeparator('ar@numbers=arab') === '٫' && CurrencyCountries::thousandsSeparator('ar@numbers=arab') === '٬', 'Arabic currency separators contain formatted digits');
$recentCalls = $suggestedCalls = $createCalls = 0;
$select->recentOptions(static function () use (&$recentCalls): array {
    $recentCalls++;

    return [1, '1', 'b'];
});
$select->suggestedOptions(static function () use (&$suggestedCalls): array {
    $suggestedCalls++;

    return ['a'];
});
$select->allowCreateOption(static function () use (&$createCalls): bool {
    $createCalls++;

    return true;
});
$snapshot = $select->getSmartSuggestConfigForJs();
ensure([$recentCalls, $suggestedCalls, $createCalls] === [1, 1, 1] && $snapshot['recent'] === [1, 'b'], 'Suggestions callbacks were repeated or identities duplicated');
invalid(fn () => OptionIdentityList::normalize([['bad']]));
invalid(fn () => OptionIdentityList::normalize(["\xff"]));
$phone = (new PhoneField('original'))->nationalDigitsOnly(static fn (PhoneField $component): bool => $component->getName() === 'clone');
$clone = clone $phone;
$clone->name('clone');
ensure(! $phone->storesNationalDigitsOnly() && $clone->storesNationalDigitsOnly(), 'Phone callback retained original field');
invalid(fn () => $phone->includeFormats(['international', []])->getIncludedFormats());
invalid(fn () => $phone->allowTypes([[]])->getAllowedTypes());
$map = (new MapPickerField('location'))->fields(['lat', 'lng', 'street', 'country', 'place_name']);
$zero = $map->hydrateToCanonical(['lat' => 0, 'lng' => '0', 'street' => '0', 'country' => 'pl']);
ensure($zero['lat'] === 0.0 && $zero['lng'] === 0.0 && $zero['place_name'] === '0, PL', 'Zero values disappeared from canonical address');
ensure($map->hydrateToCanonical(['lat' => 90.00000001])['lat'] > 90, 'Invalid coordinate rounded into legal range');
foreach ([['lat' => INF], ['lng' => NAN], ['city' => []], ['lat' => true], ['street' => "\xff"]] as $state) {
    invalid(fn () => $map->hydrateToCanonical($state));
}
$formatCalls = 0;
$map->storeFormat(static function () use (&$formatCalls): string {
    $formatCalls++;

    return 'string';
});
ensure($formatCalls === 0, 'Address storage callback evaluated during configuration');
ensure($map->dehydrateFromCanonical($zero) === '0, PL' && $formatCalls === 1, 'Dehydration did not use one format snapshot');
$address = (new AddressAutocompleteField('address'))->fields(['street', 'city', 'country', 'place_name']);
ensure($address->hydrateToCanonical('  Warsaw  ')['place_name'] === 'Warsaw', 'Legacy string address');
invalid(fn () => $address->requiredFields(['lat'])->getRequiredFields());
invalid(fn () => $address->countries(['PL', []])->getCountries());
ensure($address->countries([' pl ', 'PL'])->getCountries() === ['PL'], 'Country policy canonicalization');
invalid(fn () => $address->searchTypes([[]])->getSearchTypes());
invalid(fn () => (new MapPickerField('invalid'))->defaultCenter([INF, 0])->getDefaultCenter());
invalid(fn () => (new MapPickerField('invalid'))->defaultCenter([91, 0])->getDefaultCenter());
invalid(fn () => (new MapPickerField('invalid'))->defaultCenter(['lat' => 1, 'lng' => 2])->getDefaultCenter());
invalid(fn () => (new PhoneField('invalid'))->variant(fn () => [])->getVariant());
invalid(fn () => (new SelectField('invalid'))->optionLayout(fn () => [])->getOptionLayout());
invalid(fn () => (new CurrencyField('invalid'))->min(fn () => [])->getMin());
$catalog = [
    'first' => ['prefix' => 'fx', 'label' => 'First', 'icons' => ['fx-a', 'fx-b', 'fx-c']],
    'second' => ['prefix' => 'sx', 'label' => 'Second', 'icons' => ['sx-a', 'sx-b']],
];
$builder = new IconCatalogIndexBuilder;
foreach ([[], ['fx-a', 'fx-b', 'fx-c', 'sx-a', 'sx-b']] as $whitelist) {
    $index = $builder->build($catalog, $whitelist, ['fx-a'], 1, static fn (string $icon): string => $icon);
    ensure(! $index->isAllowed('fx-a') && $index->isAllowed('fx-b') && ! $index->isAllowed('fx-c'), 'Icon limit/exclusion policy mismatch');
    ensure(array_column($index->setSummaries(), 'count') === [1, 1], 'Icon summaries differ from admitted identities');
    ensure($index->search(set: 'fx')['icons'][0]['name'] === 'fx-b', 'Icon prefix alias lost set filtering');
    ensure($index->search(page: PHP_INT_MAX, perPage: 96)['icons'] === [], 'Large icon page overflowed');
    $first = $index->search(page: 1, perPage: 1);
    $second = $index->search(page: 2, perPage: 1);
    ensure($first['hasMore'] && ! $second['hasMore'] && $first['icons'][0]['name'] !== $second['icons'][0]['name'], 'Icon pages overlap or truncate');
}
$icons = (new PolicyIconPicker('icon'))->icons(['fx-a', 'fx-b']);
ensure($icons->isAllowedIcon('fx-a'), 'Icon initial whitelist');
$copy = clone $icons;
$copy->icons(['fx-b']);
ensure(! $copy->isAllowedIcon('fx-a') && $icons->isAllowedIcon('fx-a'), 'Icon clone policy mutated original');
invalid(fn () => $icons->searchIcons("\xff"));
echo 'PASS: Filament '.InstalledVersions::getPrettyVersion('filament/forms').'; dynamic rendering policies, AJAX scope restoration, suggestion snapshot, clone callbacks, canonical address boundaries and icon policy/pagination.'.PHP_EOL;
