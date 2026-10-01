<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Un alias no es una entidad del directorio: es un mapeo para el importador, sin
 * status. Borrar uno incorrecto es la única forma de corregirlo, así que se permite.
 */
class CityAliasPolicy extends GeographyPolicy
{
    public function delete(User $user, Model $record): bool
    {
        return $user->can('geography.manage');
    }
}
