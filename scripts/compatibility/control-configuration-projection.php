<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FragmentPlanProjection;
use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlConfiguration;

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$configuration = new RenderedControlConfiguration('flex-textarea', ['editable' => true, 'messages' => ['retry' => '<Retry & continue>'], 'shouldAutosize' => true]);
$marker = '<div data-fff-control-configuration-id="'.$configuration->id.'"></div>';
$html = '<div wire:id="probe"><input id="native-edit">'.$marker.$marker.'<textarea>literal &lt;template&gt; &amp; text</textarea></div>';
$projected = FragmentPlanProjection::project($html, [], true, [], [$configuration->id => $configuration]);
$check(substr_count($projected, 'data-fff-control-configuration-definition=') === 1, 'Repeated owners did not share one definition.');
$check(substr_count($projected, 'data-fff-control-configuration-id=') === 2, 'Projection removed independent owner references.');
$check(str_contains($projected, '<textarea>literal &lt;template&gt; &amp; text</textarea>'), 'Projection changed application text.');
$check(FragmentPlanProjection::project($projected, []) === $projected, 'Configuration projection is not idempotent.');
$nested = FragmentPlanProjection::project('<main wire:id="outer">'.$projected.'</main>', []);
$check(substr_count($nested, 'data-fff-control-configuration-definition=') === 1, 'Nested projection duplicated configuration metadata.');
$check(RenderedControlConfiguration::fromJson($configuration->json)->id === $configuration->id, 'Canonical JSON identity changed.');
$rejected = false;
try {
    new RenderedControlConfiguration('flex-textarea', ['initialState' => 'private value']);
} catch (InvalidArgumentException) {
    $rejected = true;
}
$check($rejected, 'Instance state entered the shared configuration.');
$rejected = false;
try {
    FragmentPlanProjection::project('<div>'.$marker.'</div>', []);
} catch (LogicException) {
    $rejected = true;
}
$check($rejected, 'Unknown referenced configuration did not fail.');
echo "PASS: immutable public configuration, independent owners, opaque text, self-contained/nested/idempotent fragments and missing/state rejection.\n";
