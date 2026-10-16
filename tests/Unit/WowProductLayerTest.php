<?php

declare(strict_types=1);

it('defines motion tokens in base css with reduced motion law', function (): void {
    $css = file_get_contents(__DIR__.'/../../resources/css/base.css');

    expect($css)
        ->toContain('--fff-motion-duration-fast: 150ms')
        ->toContain('--fff-motion-duration-base: 250ms')
        ->toContain('--fff-motion-duration-slow: 400ms')
        ->toContain('--fff-motion-ease-standard: cubic-bezier(0.16, 1, 0.3, 1)')
        ->toMatch('/@media \(prefers-reduced-motion: reduce\)[\s\S]*--fff-motion-duration-fast: 0\.01ms/');
});

it('documents motion tokens in the public token contract', function (): void {
    $contract = json_decode(
        (string) file_get_contents(__DIR__.'/../../resources/dist/tokens/fff-token-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($contract['tokens']['motion'])->toHaveKeys([
        '--fff-motion-duration-fast',
        '--fff-motion-duration-base',
        '--fff-motion-duration-slow',
        '--fff-motion-ease-standard',
    ]);
});
