<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Str;

/**
 * HasUlids genera ULIDs en minúsculas; el dominio `ulid` (DATABASE.md §3.2) solo acepta
 * el Crockford base32 canónico, en mayúsculas. Todo modelo con PK ulid usa este trait.
 */
trait HasUppercaseUlids
{
    use HasUlids;

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }
}
