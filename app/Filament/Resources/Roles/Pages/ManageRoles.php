<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    /**
     * Sin CreateAction: los roles los define PermissionSeeder.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
