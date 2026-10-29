<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Schemas\Schema as FormSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class RichRelationshipAuthor extends Model
{
    protected $table = 'rich_relationship_authors';

    protected $guarded = [];

    public $timestamps = false;
}

final class RichRelationshipPost extends Model
{
    protected $table = 'rich_relationship_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(RichRelationshipAuthor::class);
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(RichRelationshipAuthor::class, 'rich_relationship_author_post', 'post_id', 'author_id');
    }
}

function richRelationshipSchema(SelectField $field): FormSchema
{
    Schema::create('rich_relationship_authors', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
    });
    Schema::create('rich_relationship_posts', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('author_id')->nullable();
    });

    $schema = FormSchema::make(new TestableTranslatableForm)
        ->model(RichRelationshipPost::class)
        ->statePath('data')
        ->components([$field]);

    $schema->getComponents();

    return $schema;
}

it('renders relationship search options and saved selections from rich record arrays', function (bool $full, string $position, bool $callbackFirst): void {
    $field = SelectField::make('author_id')
        ->searchable(['name', 'email'])
        ->richListTriggerDisplay($full)
        ->optionDescriptionPosition($position);
    $callback = fn (RichRelationshipAuthor $author): array => ['label' => $author->name, 'description' => $author->email];
    if ($callbackFirst) {
        $field->getRichOptionFromRecordUsing($callback)->relationship('author', 'name');
    } else {
        $field->relationship('author', 'name')->getRichOptionFromRecordUsing($callback);
    }
    $schema = richRelationshipSchema($field);
    $author = RichRelationshipAuthor::create(['name' => 'Ada', 'email' => 'ada@example.test']);
    $post = RichRelationshipPost::create(['author_id' => $author->getKey()]);

    $search = $field->getSearchResultsForJs('ada@example');
    $schema->fill($post->fresh()->attributesToArray());
    $selected = $field->getOptionLabel();
    $initial = $field->getInitialTriggerLabel();
    $descriptionClass = $position === 'trailing' ? 'fff-select-option__trailing-description' : 'fff-select-option__description';

    expect($search)->toHaveCount(1)
        ->and($search[0]['value'])->toBe((string) $author->getKey())
        ->and($search[0]['label'])->toContain('Ada', 'ada@example.test', $descriptionClass);
    foreach ([$selected, $initial, $search[0]['triggerLabel'] ?? $search[0]['label']] as $label) {
        expect($label)->toContain('Ada');
        if ($full) {
            expect($label)->toContain('ada@example.test', $descriptionClass);
        } else {
            expect($label)->not->toContain('ada@example.test');
        }
    }
    expect($schema->getState()['author_id'])->toBe($author->getKey());
})->with([false, true], ['below', 'trailing'], [false, true]);

it('keeps relationship query constraints on search and selected labels', function (): void {
    $field = SelectField::make('author_id')
        ->relationship('author', 'name', modifyQueryUsing: fn ($query) => $query->where('name', 'Allowed'))
        ->searchable()
        ->getRichOptionFromRecordUsing(fn (Model $record): array => ['label' => $record->name, 'description' => $record->email]);
    $schema = richRelationshipSchema($field);
    $hidden = RichRelationshipAuthor::create(['name' => 'Hidden', 'email' => 'hidden@example.test']);

    $schema->fill(['author_id' => $hidden->getKey()]);

    expect($field->getSearchResultsForJs('Hidden'))->toBe([])
        ->and($field->getOptionLabel(withDefault: false))->toBeNull();
});

it('escapes rich relationship text and honors selected chip labels', function (): void {
    $field = SelectField::make('author_id')
        ->relationship('author', 'name')
        ->getRichOptionFromRecordUsing(fn (Model $record): array => [
            'label' => $record->name,
            'description' => $record->email,
            'chip_label' => 'Selected Ada',
        ]);
    $schema = richRelationshipSchema($field);
    $author = RichRelationshipAuthor::create(['name' => '<b>Ada</b>', 'email' => '<script>alert(1)</script>']);

    $options = $field->getOptionsForJs();
    $schema->fill(['author_id' => $author->getKey()]);

    expect($options[0]['label'])->toContain('&lt;b&gt;Ada&lt;/b&gt;', '&lt;script&gt;')
        ->and($options[0]['label'])->not->toContain('<script>', '<b>Ada</b>')
        ->and($field->getOptionLabel())->toBe('Selected Ada');
});

it('rejects malformed rich relationship callback results', function (mixed $result): void {
    $field = SelectField::make('author_id')->getRichOptionFromRecordUsing(fn (): mixed => $result);
    $record = (new RichRelationshipAuthor)->forceFill(['id' => 1]);

    expect(fn () => $field->getOptionLabelFromRecord($record))->toThrow(InvalidArgumentException::class);
})->with(['plain string' => ['Ada'], 'missing label' => [['description' => 'Email']]]);

it('renders and saves rich belongs to many selections through the native relationship', function (bool $full): void {
    $field = SelectField::make('authors')
        ->multiple()
        ->richListTriggerDisplay($full)
        ->relationship('authors', 'name')
        ->getRichOptionFromRecordUsing(fn (Model $record): array => ['label' => $record->name, 'description' => $record->email]);
    $schema = richRelationshipSchema($field);
    Schema::create('rich_relationship_author_post', function (Blueprint $table): void {
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('author_id');
    });
    $author = RichRelationshipAuthor::create(['name' => 'Ada', 'email' => 'ada@example.test']);
    $post = RichRelationshipPost::create();
    $schema->model($post)->fill(['authors' => [$author->getKey()]]);

    $labels = $field->getOptionLabels();
    $schema->saveRelationships();
    $freshField = clone $field;
    $freshSchema = FormSchema::make(new TestableTranslatableForm)
        ->model($post->fresh())
        ->statePath('data')
        ->components([$freshField]);
    $freshSchema->fill();
    $freshSchema->loadStateFromRelationships();
    $entries = $freshField->getHeadlessInitialOptionLabelsForJs();

    expect($post->fresh()->authors->modelKeys())->toBe([$author->getKey()])
        ->and($labels[$author->getKey()])->toContain('Ada')
        ->and($entries[0]['triggerLabel'])->toContain('Ada');
    if ($full) {
        expect($entries[0]['triggerLabel'])->toContain('ada@example.test');
    } else {
        expect($entries[0]['triggerLabel'])->not->toContain('ada@example.test');
    }
})->with([false, true]);
