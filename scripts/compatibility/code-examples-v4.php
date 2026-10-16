<?php

declare(strict_types=1);

require __DIR__.'/catalog-lazy-v4.php';

use Bjanczak\FilamentFlexFields\Filament\Livewire\CodeExamples;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexCatalogPluginPage;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExampleRepository;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Filament\Schemas\Schema;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Isolate;

function exampleCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
$app->setLocale('en');
$app['config']->set('cache.default', 'array');
$registry = app(ComponentRegistry::class);
$reader = app(CodeExampleRepository::class);
$count = 0;
foreach ($registry->all() as $id => $definition) {
    if ($definition->catalog === null) {
        continue;
    }
    exampleCheck($definition->catalog->examples !== [], 'Missing dedicated examples: '.$id);
    foreach ($reader->forComponent($id) as $example) {
        exampleCheck(! $example['unavailable'] && str_starts_with($example['source'], '<?php'), 'Unreadable example: '.$id);
        token_get_all($example['source'], TOKEN_PARSE); // Syntax verification, never executes PHP.
        exampleCheck(count($example['lines']) === substr_count($example['source'], "\n") + 1, 'Line numbers disagree: '.$id);
        $count++;
    }
}
$path = tempnam(sys_get_temp_dir(), 'flex-example-').'.php';
$marker = $path.'.executed';
try {
    $reference = CodeExample::php($path, 'Custom example');
    CodeExample::php('C:\\packages\\custom\\example.php'); // Absolute Windows packages use the same API. // File need not exist at registration.
    $registry->register(new ComponentDefinition(id: 'custom-example-probe', kind: ComponentKind::Display,
        catalog: new ComponentPresentation('Custom', 'Custom component', CatalogCategory::Controls, examples: [$reference])));
    exampleCheck($reader->forComponent('custom-example-probe')[0]['unavailable'], 'Missing custom source did not degrade gracefully.');
    $page = new FlexCatalogPluginPage;
    $page->entry = 'custom-example-probe';
    $page->form(Schema::make($page));
    exampleCheck($page->codeExampleView()->getViewData() === ['componentId' => 'custom-example-probe'], 'Parent detail page must pass only an example identity.');
    exampleCheck(! method_exists($page, 'loadCodeExamples'), 'Code loading still morphs the entire parent page.');
    Auth::setUser(new GenericUser(['id' => 1]));
    $isolated = new CodeExamples;
    $isolated->mount('custom-example-probe');
    exampleCheck($isolated->render()->getData()['examples'] === [], 'Isolated reader accessed files before activation.');
    $isolated->loadExamples();
    exampleCheck($isolated->render()->getData()['examples'][0]['unavailable'], 'Custom component without a demo cannot render example references.');
    exampleCheck((new ReflectionClass($isolated))->getAttributes(Isolate::class) !== [], 'Reader lost request isolation.');
    $source = '<?php file_put_contents('.var_export($marker, true).', "executed"); // <script>alert(1)</script>'."\n".'$color = "#123456";';
    file_put_contents($path, $source);
    $first = $reader->forComponent('custom-example-probe')[0];
    exampleCheck(! file_exists($marker), 'Example executed.');
    exampleCheck(str_contains(implode('', $first['lines']), '&lt;script&gt;') && ! str_contains(implode('', $first['lines']), '<script>'), 'Source HTML was not escaped.');
    $mtime = filemtime($path);
    file_put_contents($path, str_replace('#123456', '#654321', $source));
    touch($path, $mtime);
    $second = $reader->forComponent('custom-example-probe')[0];
    exampleCheck($first['lines'] !== $second['lines'], 'Content-addressed cache reused a same-size edit.');
    file_put_contents($path, str_repeat('x', CodeExampleRepository::MAX_BYTES + 1));
    exampleCheck($reader->forComponent('custom-example-probe')[0]['unavailable'], 'Oversized source admitted.');
    file_put_contents($path, str_repeat("line\n", CodeExampleRepository::MAX_LINES + 1));
    exampleCheck($reader->forComponent('custom-example-probe')[0]['unavailable'], 'Excessive line count admitted.');
    $view = Blade::render('filament-flex-fields::catalog.code-examples', ['loaded' => true, 'examples' => [$first]]);
    exampleCheck(str_contains($view, 'fff-code-preview__number') && str_contains($view, 'fff-code-preview__token--variable') && ! str_contains($view, '<script>alert(1)</script>'), 'Numbered preview rendered incorrectly.');
    $empty = Blade::render('filament-flex-fields::catalog.code-examples', ['loaded' => false, 'examples' => []]);
    exampleCheck(! str_contains($empty, 'flex-code-preview') && str_contains($empty, 'Loading code examples'), 'Initial code tab loaded preview assets.');
} finally {
    @unlink($path);
    @unlink($marker);
    @unlink(substr($path, 0, -4));
}
echo 'PASS: '.$count.' valid dedicated PHP files; third-party references, nonexecution, escaping, content cache invalidation, bounded reads, unavailable fallback and lazy preview rendering.'.PHP_EOL;
