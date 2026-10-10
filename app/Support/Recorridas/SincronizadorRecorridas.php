<?php

namespace App\Support\Recorridas;

use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La subida de una recorrida desde la app de campo (P4, P11; dominio.md,
 * reglas 2 a 4). Upsert de la recorrida y de cada medición por su uuid, en
 * una transacción: o entra todo o no entra nada.
 *
 * Recibe los datos ya validados en su forma (SubirRecorridaRequest); acá van
 * las reglas de negocio. `activo` nunca se toca: lo decide el panel.
 */
class SincronizadorRecorridas
{
    private const DATOS_MEDICION = [
        'altura_cm',
        'distancia_cm',
        'kg_ms_ha',
        'latitud',
        'longitud',
        'precision_m',
        'inclinacion_grados',
        'imagenes',
        'dispersion_cm',
        'calibracion',
    ];

    /**
     * @param  array<string, mixed>  $datos
     * @return array{creada: bool, mediciones: array{creadas: int, actualizadas: int, total: int}}
     *
     * @throws AuthorizationException si el uuid es de una recorrida de otro usuario
     * @throws ValidationException si el potrero no está activo o una medición es de otra recorrida
     */
    public function sincronizar(User $usuario, string $uuid, array $datos): array
    {
        return DB::transaction(function () use ($usuario, $uuid, $datos) {
            $recorrida = Recorrida::where('uuid', $uuid)->lockForUpdate()->first();

            // El dueño sale del token, nunca del pedido (regla 3).
            if ($recorrida !== null && $recorrida->user_id !== $usuario->id) {
                throw new AuthorizationException(__('api.recorrida_ajena'));
            }

            $this->exigirPotreroActivo((int) $datos['potrero_id']);

            $creada = $recorrida === null;
            $recorrida ??= new Recorrida(['uuid' => $uuid, 'user_id' => $usuario->id]);
            $recorrida->fill([
                'potrero_id' => (int) $datos['potrero_id'],
                'iniciada_en' => $this->horaLocal($datos['iniciada_en']),
                'dispositivo' => $datos['dispositivo'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ])->save();

            $creadas = 0;
            $actualizadas = 0;

            foreach (array_values($datos['mediciones'] ?? []) as $i => $punto) {
                $medicion = Medicion::where('uuid', $punto['uuid'])->first();

                if ($medicion !== null && $medicion->recorrida_id !== $recorrida->id) {
                    throw ValidationException::withMessages([
                        "mediciones.{$i}.uuid" => __('api.medicion_ajena'),
                    ]);
                }

                $medicion === null ? $creadas++ : $actualizadas++;
                $medicion ??= new Medicion(['uuid' => $punto['uuid'], 'recorrida_id' => $recorrida->id]);

                $valores = ['tomada_en' => $this->horaLocal($punto['tomada_en'])];
                foreach (self::DATOS_MEDICION as $dato) {
                    $valores[$dato] = $punto[$dato] ?? null;
                }
                $medicion->fill($valores)->save();
            }

            return [
                'creada' => $creada,
                'mediciones' => [
                    'creadas' => $creadas,
                    'actualizadas' => $actualizadas,
                    'total' => $recorrida->mediciones()->count(),
                ],
            ];
        });
    }

    /** Regla 4: solo a un potrero activo de un campo activo. */
    private function exigirPotreroActivo(int $potreroId): void
    {
        $activo = Potrero::activos()
            ->whereKey($potreroId)
            ->whereHas('campo', fn ($campo) => $campo->where('activo', true))
            ->exists();

        if (! $activo) {
            throw ValidationException::withMessages(['potrero_id' => __('api.potrero_inactivo')]);
        }
    }

    /** La app manda ISO 8601 con zona; la base guarda la hora del servidor. */
    private function horaLocal(string $fecha): Carbon
    {
        return Carbon::parse($fecha)->setTimezone(config('app.timezone'));
    }
}
