<?php

namespace App\Domain\Import\Actions;

use App\Domain\Import\Enums\ImportBatchStatus;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\Support\ImportBatchCounters;
use App\Domain\Import\Template\TemplateColumns;
use App\Domain\Import\Template\TemplateWriter;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Etapa 1 (§12.3): crea el lote y vuelca las filas CRUDAS en import_rows. No normaliza,
 * no valida datos y no toca doctors. Sí valida que el archivo sea una plantilla del
 * país correcto: un archivo ajeno no debe llegar a staging.
 */
class IngestImportFileAction
{
    /**
     * @throws ImportFileException
     */
    public function execute(string $localPath, string $originalName, Country $country, string $source, ?User $actor): ImportBatch
    {
        $source = Str::slug($source, '_') ?: config('import.default_source');
        $hash = hash_file('sha256', $localPath);

        if ($existing = ImportBatch::where('source', $source)->where('file_hash', $hash)->first()) {
            throw new ImportFileException("Este archivo ya se cargó en el lote del {$existing->created_at->format('d/m/Y H:i')}. Si cambiaste datos, guarda el archivo de nuevo antes de subirlo.");
        }

        $columns = $this->validateFile($localPath, $country);

        $batch = ImportBatch::create([
            'country_id' => $country->getKey(),
            'created_by_user_id' => $actor?->getKey(),
            'source' => $source,
            'file_name' => $originalName,
            'file_hash' => $hash,
            'status' => ImportBatchStatus::Ingesting,
            'started_at' => now(),
        ]);

        // Disco privado: el archivo contiene datos personales de médicos.
        $stored = Storage::disk('local')->putFileAs('imports', $localPath, "{$batch->getKey()}.xlsx");
        $batch->update(['file_path' => $stored]);

        try {
            $this->readRows($localPath, $columns, $batch);
        } catch (Throwable $e) {
            $batch->update(['status' => ImportBatchStatus::Failed, 'notes' => 'Error al leer el archivo: '.$e->getMessage(), 'finished_at' => now()]);

            throw $e;
        }

        activity()->performedOn($batch)->causedBy($actor)->event('import.ingested')
            ->withProperties(['file' => $originalName, 'rows' => $batch->rows()->count()])->log('import.ingested');

        return ImportBatchCounters::refresh($batch);
    }

    /**
     * Comprueba la hoja _meta (país y versión) y los encabezados.
     *
     * @return array<int, ?string> índice de columna => clave del contrato
     */
    private function validateFile(string $path, Country $country): array
    {
        $sheets = [];
        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new ImportFileException('No se pudo abrir el archivo. Debe ser un Excel (.xlsx) generado con la plantilla de MeeMedico.');
        }

        foreach ($reader->getSheetIterator() as $sheet) {
            if (! in_array($sheet->getName(), [TemplateWriter::SHEET_DATA, TemplateWriter::SHEET_META], true)) {
                continue;
            }
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
                if ($sheet->getName() === TemplateWriter::SHEET_DATA) {
                    break; // de la hoja de datos solo hace falta el encabezado
                }
            }
        }
        $reader->close();

        $meta = collect($sheets[TemplateWriter::SHEET_META] ?? [])->mapWithKeys(fn ($r) => [$r[0] ?? '' => $r[1] ?? null]);
        if ($meta->isEmpty() || ! isset($sheets[TemplateWriter::SHEET_DATA])) {
            throw new ImportFileException('El archivo no es una plantilla de MeeMedico (faltan hojas internas). Descarga la plantilla y copia los datos en ella.');
        }

        if ($meta['country'] !== $country->code) {
            throw new ImportFileException("El archivo es una plantilla de {$meta['country']} y el lote es de {$country->name} ({$country->code}). Usa la plantilla del país correcto.");
        }

        if ((int) $meta['template_version'] !== TemplateColumns::VERSION) {
            throw new ImportFileException('La plantilla es de una versión anterior. Descarga la plantilla actual y copia los datos en ella.');
        }

        $headers = $sheets[TemplateWriter::SHEET_DATA][0];
        $keys = array_map(fn ($h) => TemplateColumns::keyForHeader($country, (string) $h), $headers);

        $unknown = collect($headers)->filter(fn ($h, $i) => filled($h) && $keys[$i] === null)->values();
        $expected = collect(TemplateColumns::all($country))->pluck('key');
        $missing = $expected->diff($keys)->values();

        if ($unknown->isNotEmpty() || $missing->isNotEmpty() || count(array_filter($keys)) !== count(array_unique(array_filter($keys)))) {
            throw new ImportFileException('Los encabezados de la hoja "Médicos" fueron modificados'
                .($unknown->isNotEmpty() ? ' (no se reconocen: '.$unknown->implode(', ').')' : '')
                .'. No cambies, borres ni dupliques columnas.');
        }

        return $keys;
    }

    /**
     * @param  array<int, ?string>  $columns
     */
    private function readRows(string $path, array $columns, ImportBatch $batch): void
    {
        $reader = new Reader;
        $reader->open($path);
        $buffer = [];
        $chunk = (int) config('import.chunk_size');

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== TemplateWriter::SHEET_DATA) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                if ($rowNumber === 1) {
                    continue; // encabezado
                }

                $payload = [];
                foreach ($row->toArray() as $i => $value) {
                    if (($key = $columns[$i] ?? null) !== null && ($clean = self::cellToString($value)) !== null) {
                        $payload[$key] = $clean;
                    }
                }

                if ($payload === []) {
                    continue; // fila vacía (la plantilla trae 5.000 preparadas)
                }

                $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $buffer[] = [
                    'id' => (string) Str::ulid(),
                    'batch_id' => $batch->getKey(),
                    'row_number' => $rowNumber,
                    'raw_payload' => $json,
                    'row_hash' => hash('sha256', $json),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($buffer) >= $chunk) {
                    $this->flush($buffer);
                }
            }
        }

        $this->flush($buffer);
        $reader->close();
    }

    /**
     * Idempotente: una fila idéntica (mismo hash en el lote) no se inserta dos veces.
     *
     * @param  array<int, array<string, mixed>>  $buffer
     */
    private function flush(array &$buffer): void
    {
        if ($buffer !== []) {
            DB::table('import_rows')->insertOrIgnore($buffer);
            $buffer = [];
        }
    }

    private static function cellToString(mixed $value): ?string
    {
        $string = match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            // Excel guarda 88881234 como 88881234.0: sin decimales artificiales.
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };

        // Solo se recorta: una biografía conserva sus saltos de línea. La normalización
        // fina (espacios, acentos) es de la etapa 2, y raw_payload queda fiel al archivo.
        $string = trim(str_replace("\r\n", "\n", $string));

        return $string === '' ? null : $string;
    }
}
