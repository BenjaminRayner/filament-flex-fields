<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

/** Each scenario owns its inputs, rules and ordered calculation plan. */
final class Scenarios
{
    /** @return list<ScenarioDefinition> */
    public static function all(): array
    {
        return [
            new ScenarioDefinition('invoice', 'Invoice & tax', 'Calculate rounded tax and discounts in cents. Change an input to see the complete calculation update.', [
                'price' => ['label' => 'Unit price', 'value' => 19999, 'contract' => new NumericInput(0, 100000000, integer: true), 'money' => true],
                'quantity' => ['label' => 'Quantity', 'value' => 3, 'contract' => new NumericInput(1, 1000, integer: true)],
                'tax' => ['label' => 'Tax rate', 'value' => 23, 'contract' => new NumericInput(0, 100), 'suffix' => '%'],
                'discount' => ['label' => 'Discount', 'value' => 10, 'contract' => new NumericInput(0, 100), 'suffix' => '%'],
            ], [
                'subtotal' => '{price}*{quantity}',
                'discount_amount' => 'round({subtotal}*pct({discount}),0)',
                'net' => '{subtotal}-{discount_amount}',
                'tax_amount' => 'round({net}*pct({tax}),0)',
                'total' => '{net}+{tax_amount}',
            ], [
                'subtotal' => ['label' => 'Subtotal', 'format' => 'money'],
                'discount_amount' => ['label' => 'Discount', 'format' => 'money'],
                'net' => ['label' => 'Net amount', 'format' => 'money'],
                'tax_amount' => ['label' => 'Tax', 'format' => 'money'],
                'total' => ['label' => 'Invoice total', 'format' => 'money'],
            ]),
            new ScenarioDefinition('pricing', 'Tiered pricing', 'Use conditions for volume discounts and free shipping. A single input can update several dependent totals.', [
                'price' => ['label' => 'Unit price', 'value' => 4500, 'contract' => new NumericInput(0, 100000000, integer: true), 'money' => true],
                'quantity' => ['label' => 'Units', 'value' => 12, 'contract' => new NumericInput(1, 1000, integer: true)],
                'shipping' => ['label' => 'Standard shipping', 'value' => 1500, 'contract' => new NumericInput(0, 100000, integer: true), 'money' => true],
            ], [
                'discount_rate' => 'if({quantity}>=20,15,if({quantity}>=10,10,0))',
                'net' => 'round({price}*{quantity}*(1-pct({discount_rate})),0)',
                'delivery' => 'if({net}>=50000,0,{shipping})',
                'total' => '{net}+{delivery}',
            ], [
                'discount_rate' => ['label' => 'Volume discount', 'format' => 'percent'],
                'net' => ['label' => 'After discount', 'format' => 'money'],
                'delivery' => ['label' => 'Shipping', 'format' => 'money'],
                'total' => ['label' => 'Order total', 'format' => 'money'],
            ]),
            new ScenarioDefinition('progress', 'Progress & safe division', 'A zero target is valid here: the conditional formula skips division and returns zero. Progress stays between 0% and 100%.', [
                'completed' => ['label' => 'Completed', 'value' => 18, 'contract' => new NumericInput(0, 10000, integer: true)],
                'target' => ['label' => 'Target', 'value' => 24, 'contract' => new NumericInput(0, 10000, integer: true)],
            ], [
                'remaining' => 'max({target}-{completed},0)',
                'progress' => 'if({target}>0,clamp(round({completed}/{target}*100,2),0,100),0)',
                'complete' => 'and({target}>0,{completed}>={target})',
            ], [
                'remaining' => ['label' => 'Remaining'],
                'progress' => ['label' => 'Progress', 'format' => 'percent'],
                'complete' => ['label' => 'Target reached', 'format' => 'boolean'],
            ]),
            new ScenarioDefinition('margin', 'Profit & margin', 'Calculate profit and margin from the same inputs. Revenue can be zero without producing a division error.', [
                'revenue' => ['label' => 'Revenue', 'value' => 1200000, 'contract' => new NumericInput(0, 100000000, integer: true), 'money' => true],
                'cost' => ['label' => 'Cost', 'value' => 780000, 'contract' => new NumericInput(0, 100000000, integer: true), 'money' => true],
            ], [
                'profit' => '{revenue}-{cost}',
                'margin' => 'if({revenue}>0,round({profit}/{revenue}*100,2),0)',
                'profitable' => '{profit}>0',
            ], [
                'profit' => ['label' => 'Profit', 'format' => 'money'],
                'margin' => ['label' => 'Margin', 'format' => 'percent'],
                'profitable' => ['label' => 'Profitable', 'format' => 'boolean'],
            ]),
            new ScenarioDefinition('inventory', 'Stock & replenishment', 'Combine capacity limits, thresholds and whole-number rounding to calculate the next purchase order.', [
                'stock' => ['label' => 'Available stock', 'value' => 18, 'contract' => new NumericInput(0, 100000, integer: true)],
                'reserved' => ['label' => 'Reserved', 'value' => 8, 'contract' => new NumericInput(0, 100000, integer: true)],
                'target' => ['label' => 'Target stock', 'value' => 60, 'contract' => new NumericInput(0, 100000, integer: true)],
                'pack' => ['label' => 'Units per pack', 'value' => 12, 'contract' => new NumericInput(1, 1000, integer: true)],
            ], [
                'available' => 'max({stock}-{reserved},0)',
                'shortfall' => 'max({target}-{available},0)',
                'packs' => 'ceil({shortfall}/{pack})',
                'purchase' => '{packs}*{pack}',
            ], [
                'available' => ['label' => 'Unreserved stock'],
                'shortfall' => ['label' => 'Shortfall'],
                'packs' => ['label' => 'Packs to order'],
                'purchase' => ['label' => 'Units to purchase'],
            ]),
        ];
    }
}
