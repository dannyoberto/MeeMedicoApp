<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * DATABASE.md §16.3.
 */
class LanguageSeeder extends Seeder
{
    public const LANGUAGES = [
        'es' => 'Español',
        'en' => 'Inglés',
        'fr' => 'Francés',
        'pt' => 'Portugués',
    ];

    public function run(): void
    {
        foreach (self::LANGUAGES as $code => $name) {
            Language::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
