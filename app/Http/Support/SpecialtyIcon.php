<?php

namespace App\Http\Support;

/**
 * Icono de cada especialidad en el sitio público (Heroicons, design-system §13).
 * Heroicons no es un set médico: es una aproximación reconocible, no un símbolo
 * clínico. El escudo con check NO se usa: es solo "verificado" (§4.4).
 *
 * Una especialidad nueva sin entrada aquí usa el icono genérico.
 */
final class SpecialtyIcon
{
    private const ICONS = [
        'alergologia' => 'sparkles',
        'anestesiologia' => 'moon',
        'cardiologia' => 'heart',
        'cirugia-cardiovascular' => 'heart',
        'cirugia-general' => 'scissors',
        'cirugia-plastica' => 'scissors',
        'cirugia-vascular' => 'scissors',
        'dermatologia' => 'sun',
        'endocrinologia' => 'scale',
        'gastroenterologia' => 'beaker',
        'geriatria' => 'user',
        'ginecologia-y-obstetricia' => 'user-group',
        'hematologia' => 'eye-dropper',
        'infectologia' => 'bug-ant',
        'medicina-de-emergencias' => 'bell-alert',
        'medicina-familiar' => 'home',
        'medicina-fisica-y-rehabilitacion' => 'arrow-path',
        'medicina-general' => 'user',
        'medicina-interna' => 'clipboard-document-list',
        'nefrologia' => 'beaker',
        'neumologia' => 'cloud',
        'neurocirugia' => 'light-bulb',
        'neurologia' => 'light-bulb',
        'oftalmologia' => 'eye',
        'oncologia' => 'lifebuoy',
        'ortopedia-y-traumatologia' => 'hand-raised',
        'otorrinolaringologia' => 'speaker-wave',
        'pediatria' => 'face-smile',
        'psiquiatria' => 'chat-bubble-oval-left-ellipsis',
        'radiologia' => 'viewfinder-circle',
        'reumatologia' => 'hand-raised',
        'urologia' => 'beaker',
    ];

    public static function for(string $slug): string
    {
        return 'heroicon-o-'.(self::ICONS[$slug] ?? 'plus-circle');
    }
}
