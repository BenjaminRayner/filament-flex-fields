<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Support\DateTime\ScheduleV2;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDays;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleNormalizer;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleValidator;

it('exposes schedule configuration via fluent api', function () {
    $field = ScheduleField::make('hours')
        ->days(['mon', 'tue', 'wed'])
        ->timezone('Europe/Warsaw')
        ->timeStep(30)
        ->minSlots(2)
        ->maxSlots(5)
        ->allowCopyToWeekdays(false)
        ->copySourceDay('tue')
        ->size('lg')
        ->variant('secondary');

    expect($field->getDays())->toBe(['mon', 'tue', 'wed'])
        ->and($field->getDefaultTimezoneIdentifier())->toBe('Europe/Warsaw')
        ->and($field->showsTimezoneSelector())->toBeTrue()
        ->and($field->getTimeStep())->toBe(30)
        ->and($field->getMinSlots())->toBe(2)
        ->and($field->getMaxSlots())->toBe(5)
        ->and($field->shouldAllowCopyToWeekdays())->toBeFalse()
        ->and($field->getCopySourceDay())->toBe('tue')
        ->and($field->getSize())->toBe('lg')
        ->and($field->getVariant())->toBe('secondary');
});

it('defaults time step to five minutes', function () {
    expect(ScheduleField::make('hours')->getTimeStep())->toBe(5);
});

it('defaults control size to small', function () {
    expect(ScheduleField::make('hours')->getSize())->toBe('sm');
});

it('treats schedule as empty when no enabled day has slots', function () {
    $field = ScheduleField::make('hours')->timezone('UTC');

    $allClosed = [
        'timezone' => 'UTC',
        'days' => array_fill_keys(ScheduleDays::ALL, [
            'enabled' => false,
            'slots' => [],
        ]),
    ];

    expect($field->isEmptyState($allClosed))->toBeTrue();

    $enabledWithoutSlots = $allClosed;
    $enabledWithoutSlots['days']['mon']['enabled'] = true;
    $enabledWithoutSlots['days']['mon']['slots'] = [];

    expect($field->isEmptyState($enabledWithoutSlots))->toBeTrue();

    $validSchedule = ScheduleField::defaultSchedule('UTC');

    expect($field->isEmptyState($validSchedule))->toBeFalse();
});

it('returns no locked days by default', function () {
    expect(ScheduleField::make('hours')->getLockedDays())->toBe([])
        ->and(ScheduleField::make('hours')->getAlpineConfiguration()['lockedDays'])->toBe([]);
});

it('supports locked days configuration', function () {
    $field = ScheduleField::make('hours')
        ->days(['mon', 'tue', 'sat'])
        ->lockedDays(['sat', 'SUN', 'invalid']);

    expect($field->getLockedDays())->toBe(['sat'])
        ->and($field->isDayLocked('sat'))->toBeTrue()
        ->and($field->isDayLocked('mon'))->toBeFalse();

    $config = $field->getAlpineConfiguration();

    expect($config['lockedDays'])->toBe(['sat']);
});

it('defaults workdays to monday through friday', function () {
    expect(ScheduleField::make('hours')->getWorkdays())->toBe(ScheduleDays::WEEKDAYS);
});

it('supports configurable workdays for copy to weekdays', function () {
    $field = ScheduleField::make('hours')
        ->workdays(['mon', 'tue', 'wed', 'thu', 'fri'])
        ->copySourceDay('mon');

    expect($field->getWorkdays())->toBe(ScheduleDays::WEEKDAYS);

    $field->workdays(['mon', 'wed', 'fri', 'invalid']);

    expect($field->getWorkdays())->toBe(['mon', 'wed', 'fri'])
        ->and($field->getAlpineConfiguration()['weekdays'])->toBe(['mon', 'wed', 'fri']);
});

it('hides timezone selector when timezone is null', function () {
    $field = ScheduleField::make('hours')->timezone(null);

    expect($field->showsTimezoneSelector())->toBeFalse()
        ->and($field->getDefaultTimezoneIdentifier())->toBeNull();
});

it('creates a default weekday schedule', function () {
    $schedule = ScheduleField::defaultSchedule('UTC');

    expect($schedule['timezone'])->toBe('UTC')
        ->and($schedule['days']['mon']['enabled'])->toBeTrue()
        ->and($schedule['days']['mon']['slots'])->toBe([['from' => '09:00', 'to' => '17:00']])
        ->and($schedule['days']['sat']['enabled'])->toBeFalse()
        ->and($schedule['days']['sun']['enabled'])->toBeFalse();
});

it('normalizes invalid schedule state', function () {
    $field = ScheduleField::make('hours')->timezone('UTC');

    $normalized = $field->normalizeState([
        'timezone' => 'UTC',
        'days' => [
            'mon' => [
                'enabled' => true,
                'slots' => [
                    ['from' => '9:00', 'to' => '17:00'],
                    ['from' => 'bad', 'to' => '18:00'],
                ],
            ],
        ],
    ]);

    expect($normalized['days']['mon']['slots'])->toBe([['from' => '09:00', 'to' => '17:00', 'type' => 'slot']])
        ->and($normalized['days']['tue']['enabled'])->toBeFalse();
});

it('preserves slot and break types during normalization', function () {
    $normalizer = new ScheduleNormalizer;

    $normalized = $normalizer->normalizeDay([
        'enabled' => true,
        'slots' => [
            ['from' => '09:00', 'to' => '12:00'],
            ['from' => '12:00', 'to' => '13:00', 'type' => 'break'],
            ['from' => '13:00', 'to' => '17:00', 'type' => 'slot'],
        ],
    ]);

    expect($normalized['slots'])->toBe([
        ['from' => '09:00', 'to' => '12:00', 'type' => 'slot'],
        ['from' => '12:00', 'to' => '13:00', 'type' => 'break'],
        ['from' => '13:00', 'to' => '17:00', 'type' => 'slot'],
    ]);
});

it('detects overlapping slots', function () {
    $validator = new ScheduleValidator;

    expect($validator->slotsOverlap([
        ['from' => '09:00', 'to' => '12:00'],
        ['from' => '11:00', 'to' => '13:00'],
    ]))->toBeTrue()
        ->and($validator->slotsOverlap([
            ['from' => '09:00', 'to' => '12:00'],
            ['from' => '12:00', 'to' => '13:00'],
        ]))->toBeFalse();
});

it('fails validation when enabled day has no slots', function () {
    $validator = new ScheduleValidator(minSlots: 1);
    $errors = [];

    $days = [];

    foreach (ScheduleDays::ALL as $day) {
        $days[$day] = [
            'enabled' => $day === 'mon',
            'slots' => [],
        ];
    }

    $validator->validate([
        'timezone' => 'UTC',
        'days' => $days,
    ], ScheduleDays::ALL, function (string $message) use (&$errors): void {
        $errors[] = $message;
    }, 'UTC', timezone_identifiers_list());

    expect($errors)->not->toBeEmpty();
});

it('passes validation for a valid split shift schedule', function () {
    $field = ScheduleField::make('hours')->timezone('UTC');
    $validator = new ScheduleValidator;
    $errors = [];

    $state = ScheduleField::defaultSchedule('UTC');
    $state['days']['mon']['slots'] = [
        ['from' => '09:00', 'to' => '12:00'],
        ['from' => '13:00', 'to' => '17:00'],
    ];

    $validator->validate(
        $state,
        ScheduleDays::ALL,
        function (string $message) use (&$errors): void {
            $errors[] = $message;
        },
        'UTC',
        $field->getResolvedTimezoneIdentifiers(),
    );

    expect($errors)->toBe([]);
});

it('fails validation when enabled day exceeds max slots', function () {
    $validator = new ScheduleValidator(minSlots: 1, maxSlots: 6);
    $errors = [];

    $days = [];

    foreach (ScheduleDays::ALL as $day) {
        $days[$day] = [
            'enabled' => $day === 'mon',
            'slots' => $day === 'mon'
                ? array_map(
                    fn (int $index): array => [
                        'from' => sprintf('%02d:00', 8 + $index),
                        'to' => sprintf('%02d:30', 8 + $index),
                    ],
                    range(0, 6),
                )
                : [],
        ];
    }

    $validator->validate([
        'timezone' => 'UTC',
        'days' => $days,
    ], ScheduleDays::ALL, function (string $message) use (&$errors): void {
        $errors[] = $message;
    }, 'UTC', timezone_identifiers_list());

    expect($errors)->not->toBeEmpty();
});

it('fails validation when slots overlap on an enabled day', function () {
    $validator = new ScheduleValidator;
    $errors = [];

    $state = ScheduleField::defaultSchedule('UTC');
    $state['days']['mon']['slots'] = [
        ['from' => '09:00', 'to' => '12:00'],
        ['from' => '11:00', 'to' => '13:00'],
    ];

    $validator->validate(
        $state,
        ScheduleDays::ALL,
        function (string $message) use (&$errors): void {
            $errors[] = $message;
        },
        'UTC',
        timezone_identifiers_list(),
    );

    expect($errors)->not->toBeEmpty();
});

it('rejects overnight interval overlaps detected by ScheduleV2', function () {
    $validator = new ScheduleValidator;
    $errors = [];

    $state = ScheduleField::defaultSchedule('UTC');
    $state['days']['mon']['slots'] = [
        ['from' => '22:00', 'to' => '06:00', 'overnight' => true],
        ['from' => '23:30', 'to' => '23:45'],
    ];

    expect(ScheduleV2::validateNoOverlap([
        ['from' => '22:00', 'to' => '06:00', 'overnight' => true],
        ['from' => '23:30', 'to' => '23:45'],
    ]))->toBeFalse();

    $validator->validate(
        $state,
        ScheduleDays::ALL,
        function (string $message) use (&$errors): void {
            $errors[] = $message;
        },
        'UTC',
        timezone_identifiers_list(),
    );

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain(__('filament-flex-fields::default.schedule.days.mon'));
});

it('allows non-overlapping overnight intervals via ScheduleV2', function () {
    $validator = new ScheduleValidator;
    $errors = [];

    $state = ScheduleField::defaultSchedule('UTC');
    $state['days']['mon']['slots'] = [
        ['from' => '22:00', 'to' => '06:00', 'overnight' => true],
        ['from' => '07:00', 'to' => '08:00'],
    ];

    $validator->validate(
        $state,
        ScheduleDays::ALL,
        function (string $message) use (&$errors): void {
            $errors[] = $message;
        },
        'UTC',
        timezone_identifiers_list(),
    );

    expect($errors)->toBe([]);
});

it('exposes slot limits and validation messages in alpine configuration', function () {
    $field = ScheduleField::make('hours')
        ->timezone('UTC')
        ->minSlots(1)
        ->maxSlots(6)
        ->requireSlotsForEnabledDays(true);

    $config = $field->getAlpineConfiguration();

    expect($config['minSlots'])->toBe(1)
        ->and($config['maxSlots'])->toBe(6)
        ->and($config['requireSlotsForEnabledDays'])->toBeTrue()
        ->and($config['validationMessages']['overlap'])->toBe(__('filament-flex-fields::default.schedule.ui.overlap'))
        ->and($config['validationMessages']['overlap'])->not->toContain('filament-flex-fields::');
});

it('dehydrates normalized schedule state for storage', function () {
    $field = ScheduleField::make('hours')->timezone('Europe/Warsaw');

    $normalized = $field->normalizeState([
        'timezone' => 'Europe/Warsaw',
        'days' => [
            'mon' => [
                'enabled' => true,
                'slots' => [
                    ['from' => '9:00', 'to' => '12:00'],
                    ['from' => '12:00', 'to' => '13:00', 'type' => 'break'],
                ],
            ],
        ],
    ]);

    expect($normalized['timezone'])->toBe('Europe/Warsaw')
        ->and($normalized['days']['mon']['enabled'])->toBeTrue()
        ->and($normalized['days']['mon']['slots'])->toBe([
            ['from' => '09:00', 'to' => '12:00', 'type' => 'slot'],
            ['from' => '12:00', 'to' => '13:00', 'type' => 'break'],
        ])
        ->and($normalized['days']['tue']['enabled'])->toBeFalse()
        ->and($normalized['days']['tue']['slots'])->toBe([])
        ->and(array_keys($normalized['days']))->toBe(ScheduleDays::ALL);
});

it('includes alpine configuration labels and days', function () {
    $field = ScheduleField::make('hours')->timezone('UTC');
    $config = $field->getAlpineConfiguration();

    expect($config['days'])->toBe(ScheduleDays::ALL)
        ->and($config['showTimezone'])->toBeTrue()
        ->and($config['copySourceDay'])->toBe('mon')
        ->and($config['labels']['addSlot'])->toBe(__('filament-flex-fields::default.schedule.add_slot'))
        ->and($config)->not->toHaveKey('flexTimeSegmentsSrc');
});

it('normalizes day keys and rejects invalid copy source day', function () {
    $field = ScheduleField::make('hours')->days(['Mon', 'TUE', 'invalid', 'mon']);

    expect($field->getDays())->toBe(['mon', 'tue']);

    $field->copySourceDay('invalid');

    $field->getCopySourceDay();
})->throws(InvalidArgumentException::class);

it('normalizes time values through schedule normalizer', function () {
    $normalizer = new ScheduleNormalizer;

    expect($normalizer->normalizeTime('09:05'))->toBe('09:05')
        ->and($normalizer->normalizeTime('25:00'))->toBeNull();
});

it('clamps time step between one and sixty minutes', function () {
    $field = ScheduleField::make('hours')->timeStep(120);

    expect($field->getTimeStep())->toBe(60);

    $field->timeStep(0);

    expect($field->getTimeStep())->toBe(1);
});

it('exposes wrapper classes for size and variant', function () {
    $field = ScheduleField::make('hours')->size('sm')->variant('soft');

    expect($field->getWrapperClasses())->toMatchArray([
        'fff-schedule-field' => true,
        'fff-schedule-field--sm' => true,
        'fff-schedule-field--soft' => true,
    ]);
});
