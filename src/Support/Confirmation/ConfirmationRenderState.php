<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Confirmation;

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use LogicException;

final readonly class ConfirmationRenderState
{
    /** @param array<string, mixed> $configuration */
    private function __construct(public array $configuration, public string $consumer, public string $key) {}

    public static function from(Action $action): self
    {
        $config = $action->getConfirmationConfiguration();
        $base = FlexFieldAssets::resolveAssetConsumerLivewireKey('hold-confirm-action', $action->getConfirmationConsumerKey());
        $identity = $base.'.'.hash('sha256', serialize([$action->getName(), $config['context'], $config['arguments']]));
        $scope = RequestState::resolve(ConfirmationRenderScope::class);
        if (count($scope->occurrences) >= 4096 && ! isset($scope->occurrences[$identity])) {
            throw new LogicException('Confirmation render identity budget exceeded.');
        }
        $occurrence = $scope->occurrences[$identity] = ($scope->occurrences[$identity] ?? 0) + 1;
        $consumer = $identity.'.'.$occurrence;

        return new self($config, $consumer, $consumer.'.'.hash('sha256', serialize($config)));
    }
}
