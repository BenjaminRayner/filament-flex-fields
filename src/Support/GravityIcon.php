<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

/** Stable semantic icon names backed exclusively by blade-hugeicons. */
/** @deprecated Use FlexIcon. Kept only for consumers of the previous PHP API. */
class_alias(FlexIcon::class, __NAMESPACE__.'\\GravityIcon');
