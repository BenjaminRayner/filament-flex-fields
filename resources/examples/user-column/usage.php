<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\UserColumn;

UserColumn::make('author')->nameColumn('name')->emailColumn('email')->verificationColumn('email_verified_at');
UserColumn::make('members')->nameColumn('name')->maxVisibleAvatars(4)->stackedRing(2)->stackedOverlap(10);
