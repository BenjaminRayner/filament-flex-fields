<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Concerns;

use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Filament\Infolists\Components\Entry;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/** One consumer per rendered table cell or infolist entry, including repeated records. */
trait EmitsFlexDisplayAssets
{
    protected bool $hasCriticalFlexDisplayStyles = false;

    abstract protected function flexDisplayComponentId(): string;

    public function criticalStyles(bool $condition = true): static
    {
        $this->hasCriticalFlexDisplayStyles = $condition;

        return $this;
    }

    public function toHtml(): string
    {
        $component = $this->flexDisplayComponentId();
        if ($this->hasCriticalFlexDisplayStyles) {
            FlexFieldStylesheetQueue::requireCritical($component);
        }
        $owner = $this->getLivewire();
        $record = $this->getRecord();
        $recordId = spl_object_id($this);
        if ($this instanceof Column && $owner instanceof HasTable && $record !== null) {
            $recordId = $owner->getTableRecordKey($record);
        } elseif ($record instanceof Model) {
            $recordId = $record->getMorphClass().':'.$record->getKey();
        } elseif (is_array($record)) {
            $key = $record[ArrayRecord::getKeyName()] ?? null;
            if (is_string($key) || is_int($key)) {
                $recordId = $key;
            }
        }
        $instanceKey = $this instanceof Entry ? ($this->getKey() ?? $this->getStatePath()) : $this->getName();
        $identity = ($owner instanceof Component ? $owner->getId() : 'page').'.'.$component.'.'.$instanceKey.'.'.$recordId;

        return FlexFieldStylesheetQueue::emitDomRootConsumerMarkup($component, hash('xxh128', $identity), $this->hasCriticalFlexDisplayStyles)
            .parent::toHtml();
    }
}
