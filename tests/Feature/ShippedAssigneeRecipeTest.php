<?php

declare(strict_types=1);

use App\Support\AssigneePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    require_once dirname(__DIR__, 2).'/resources/recipes/scoped-select/app/Support/AssigneePicker.php';
    Schema::create('recipe_assignees', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('team_id')->index();
        $table->string('name');
    });
    $rows = [];
    for ($i = 1; $i <= 10000; $i++) {
        $rows[] = ['id' => $i, 'team_id' => $i % 2, 'name' => 'Person '.str_pad((string) $i, 5, '0', STR_PAD_LEFT)];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('recipe_assignees')->insert($chunk);
    }
});

it('pages the real 10000-row recipe without materializing unauthorized rows or duplicates', function (): void {
    $picker = new AssigneePicker(fn () => RecipeAssignee::query()->where('team_id', 1));
    expect($picker->field())->toBeInstanceOf(SelectField::class);
    $cursor = null;
    $ids = [];
    $pages = 0;
    do {
        $result = $picker->page('', $cursor, 40);
        expect(count($result['items']))->toBeLessThanOrEqual(40);
        foreach ($result['items'] as $item) {
            expect((int) $item['value'] % 2)->toBe(1);
            $ids[] = $item['value'];
        }
        if ($result['hasMore']) {
            expect((int) $result['cursor'])->toBeGreaterThan((int) $cursor);
        }
        $cursor = $result['cursor'];
        $pages++;
    } while ($result['hasMore']);
    expect($ids)->toHaveCount(5000)->and(array_unique($ids))->toHaveCount(5000)
        ->and($pages)->toBe(125)->and($cursor)->toBeNull();
});

it('uses the same current scope for labels and submitted IDs including revoked access', function (): void {
    $team = 1;
    $picker = new AssigneePicker(function () use (&$team) {
        return RecipeAssignee::query()->where('team_id', $team);
    });
    expect($picker->label('1'))->toBe('Person 00001')
        ->and($picker->label('2'))->toBeNull()
        ->and(Validator::make(['id' => '2'], ['id' => [$picker->rule()]])->fails())->toBeTrue()
        ->and(Validator::make(['id' => '1'], ['id' => [$picker->rule()]])->passes())->toBeTrue();
    $team = 0;
    expect($picker->label('1'))->toBeNull()
        ->and(Validator::make(['id' => '1'], ['id' => [$picker->rule()]])->fails())->toBeTrue();
});

it('bounds cursor input and searches wildcard characters literally', function (): void {
    $picker = new AssigneePicker(fn () => RecipeAssignee::query()->where('team_id', 1));
    foreach (['0', '-1', '01', '1 OR 1=1', '9999999999999999999'] as $cursor) {
        expect(fn () => $picker->page('', $cursor, 40))->toThrow(ValidationException::class)
            ->and($picker->label($cursor))->toBeNull();
    }
    expect(fn () => $picker->page('', null, 101))->toThrow(ValidationException::class)
        ->and(fn () => $picker->page(str_repeat('a', 121), null, 40))->toThrow(ValidationException::class)
        ->and($picker->page('%', null, 40)['items'])->toBeEmpty()
        ->and($picker->page('_', null, 40)['items'])->toBeEmpty();
    DB::table('recipe_assignees')->where('id', 1)->update(['name' => '%Literal']);
    expect($picker->page('%', null, 40)['items'])->toBe([['value' => '1', 'label' => '%Literal']]);
});

class RecipeAssignee extends Model
{
    protected $table = 'recipe_assignees';
}
