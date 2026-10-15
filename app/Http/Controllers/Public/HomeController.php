<?php

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Geo\Enums\CountryStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\View\View;

/**
 * Portada: elegir país. Sin redirección por geolocalización: la página tiene que ser
 * la misma para todos para poder cachearla en el CDN.
 */
final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $countries = Country::where('status', CountryStatus::Active)
            ->withCount(['doctors' => fn ($q) => $q->where('status', DoctorStatus::Active)])
            ->orderBy('name')
            ->get();

        return view('public.home', [
            'countries' => $countries,
            'seo' => [
                'title' => 'MeeMedico: encuentra a tu médico y llámalo directo',
                'description' => 'Directorio de médicos'
                    .($countries->isEmpty() ? '' : ' de '.$countries->pluck('name')->join(', ', ' y '))
                    .'. Busca por especialidad y ciudad y contacta sin intermediarios.',
                'canonical' => url('/'),
                'noindex' => false,
            ],
        ]);
    }
}
