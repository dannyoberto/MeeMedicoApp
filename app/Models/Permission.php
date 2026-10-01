<?php

namespace App\Models;

use App\Models\Concerns\HasUppercaseUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUppercaseUlids;
}
