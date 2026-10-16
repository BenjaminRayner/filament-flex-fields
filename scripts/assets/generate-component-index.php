<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use Bjanczak\FilamentFlexFields\Support\Assets\Build\BuiltInProjection;

$source = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Bjanczak\\FilamentFlexFields\\Support\\Assets;\n\n/** Generated compatibility constants. Edit resources/components, never this file. */\nfinal class BuiltInComponentIndex\n{\n";
$projection = BuiltInProjection::compile();
foreach ($projection as $name => $value) {
    $source .= '    public const '.$name.' = '.var_export($value, true).";\n\n";
}
$source .= "}\n";
$target = __DIR__.'/../../src/Support/Assets/BuiltInComponentIndex.php';
$temporary = $target.'.'.getmypid().'.tmp';
try {
    if (file_put_contents($temporary, $source) !== strlen($source) || ! rename($temporary, $target)) {
        throw new RuntimeException('Cannot atomically write the generated component projection.');
    }
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}
echo 'Compiled '.count($projection['STYLESHEET_DEPENDENCIES'])." component definitions.\n";
