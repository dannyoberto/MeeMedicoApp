<?php

namespace App\Filament\Resources\Insurers\Pages;

use App\Filament\Actions\ChangeSlugAction;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Insurers\InsurerResource;
use App\Models\Insurer;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Insurer $record
 */
class EditInsurer extends EditRecord
{
    protected static string $resource = InsurerResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return "{$this->record->type?->getLabel()} · {$this->record->country?->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            ChangeSlugAction::make(),
            ToggleStatusAction::make(),
        ];
    }
}
