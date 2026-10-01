<?php

namespace App\Filament\Support;

use Filament\Support\Colors\Color;

/**
 * Paleta del backoffice = primitivos de resources/css/tokens.css (design-system.md §3.1).
 * Duplicación deliberada y acotada: si cambia un color de marca, cambia en ambos
 * archivos en el mismo commit. Filament convierte cada valor a oklch.
 */
final class BrandColors
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            'primary' => self::PETROL,
            'gray' => self::GRAY,
            'premium' => self::GOLD,
            // Los tonos de estado solo definen 50/200/600/800 en tokens.css: el resto
            // de la escala se genera y esos cuatro se fijan a los valores exactos.
            'success' => self::state('#1E7A4C', ['#EAF6EF', '#B9DFC8', '#124D30']),
            'warning' => self::state('#A86A0B', ['#FDF6E7', '#F3D596', '#6B4306']),
            'danger' => self::state('#B42318', ['#FDEEEC', '#F4BDB6', '#6F1A14']),
            'info' => self::state('#1F5FA8', ['#EAF2FB', '#B5D0EE', '#163E6B']),
        ];
    }

    private const PETROL = [
        50 => '#EEF5F6', 100 => '#D5E6EA', 200 => '#ABCDD5', 300 => '#7AAEBA',
        400 => '#4A8C9C', 500 => '#2E7384', 600 => '#1B5E6F', 700 => '#164D5B',
        800 => '#123C4A', 900 => '#0D2E39', 950 => '#081E26',
    ];

    private const GRAY = [
        50 => '#F6F8F9', 100 => '#EDF1F3', 200 => '#DDE3E7', 300 => '#C5CED4',
        400 => '#9AA7AF', 500 => '#647179', 600 => '#56636B', 700 => '#414D55',
        800 => '#2B353B', 900 => '#1A2328', 950 => '#0F161A',
    ];

    private const GOLD = [
        50 => '#FBF7EC', 100 => '#F5EACB', 200 => '#EBD69A', 300 => '#E0C27A',
        400 => '#D4AF62', 500 => '#C29A4C', 600 => '#A6803A', 700 => '#8A6D2F',
        800 => '#6E5520', 900 => '#533F17', 950 => '#3A2C10',
    ];

    /**
     * @param  array{0: string, 1: string, 2: string}  $fixed  tonos 50, 200 y 800
     * @return array<int, string>
     */
    private static function state(string $solid, array $fixed): array
    {
        return array_replace(Color::hex($solid), [
            50 => $fixed[0],
            200 => $fixed[1],
            600 => $solid,
            800 => $fixed[2],
        ]);
    }
}
