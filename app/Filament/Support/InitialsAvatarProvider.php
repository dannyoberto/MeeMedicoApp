<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatar de iniciales generado en el servidor (SVG en data URI).
 * Sustituye a UiAvatarsProvider, que pide la imagen a ui-avatars.com y le envía
 * el nombre de cada usuario a un tercero.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        return self::dataUri(Filament::getNameForDefaultAvatar($record));
    }

    public static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        $initials = count($words) >= 2
            ? mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)
            : mb_substr($words[0] ?? '?', 0, 2);

        return Str::upper($initials);
    }

    /**
     * petrol-800 con texto blanco, como el mockup del backoffice.
     */
    public static function dataUri(string $name): string
    {
        $initials = e(self::initials($name));

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#123C4A"/><text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Inter, system-ui, sans-serif" font-size="26" font-weight="600" fill="#FFFFFF">{$initials}</text></svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
