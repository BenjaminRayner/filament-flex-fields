<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Media;

use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/** Native presentation is retained; each cell/entry still owns its FLEX boundary. */
trait EmitsMediaConsumer
{
    abstract protected function mediaComponentId(): string;

    public function toHtml(): string
    {
        $record = $this->getRecord();
        $owner = $this->getLivewire();
        $recordId = $record instanceof Model ? $record->getMorphClass().':'.$record->getKey() : spl_object_id($this);
        if ($this instanceof Column && $record !== null) {
            $recordId = $this->getLivewire()->getTableRecordKey($record);
        } elseif (is_array($record)) {
            $key = $record[ArrayRecord::getKeyName()] ?? null;
            $recordId = is_string($key) || is_int($key) ? $key : spl_object_id($this);
        }
        $identity = ($owner instanceof Component ? $owner->getId() : 'page').'.'.$this->getName().'.'
            .$recordId;

        return FlexFieldStylesheetQueue::emitDomRootConsumerMarkup($this->mediaComponentId(), hash('xxh128', $identity), false)
            .parent::toHtml();
    }
}
