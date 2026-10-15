<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Directory\Actions\RecordContactEventAction;
use App\Domain\Directory\Enums\ContactEventSource;
use App\Domain\Directory\Enums\ContactEventType;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/contact-events. Lo llama el beacon de contacto (navigator.sendBeacon)
 * de las páginas públicas; mañana, la app móvil. Sin sesión ni cookies.
 *
 * Responde 204 sin cuerpo: el beacon no lee la respuesta. Un médico no publicado es
 * 422, no 404, para no revelar qué identificadores existen.
 */
final class ContactEventController extends Controller
{
    public function __invoke(Request $request, RecordContactEventAction $record): Response
    {
        $data = $request->validate([
            'doctor' => ['required', 'string', 'size:26'],
            'type' => ['required', Rule::enum(ContactEventType::class)],
            'source' => ['sometimes', Rule::enum(ContactEventSource::class)],
        ]);

        $doctor = Doctor::find($data['doctor']);

        if ($doctor === null) {
            return response()->noContent(422);
        }

        try {
            $record->execute(
                $doctor,
                ContactEventType::from($data['type']),
                ContactEventSource::from($data['source'] ?? ContactEventSource::Profile->value),
                $request->ip().'|'.$request->userAgent(),
            );
        } catch (DirectoryRuleException) {
            return response()->noContent(422);
        }

        return response()->noContent();
    }
}
