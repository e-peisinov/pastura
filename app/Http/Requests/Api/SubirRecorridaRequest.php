<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La forma del JSON que sube la app de campo (P11). Las reglas de negocio
 * (pertenencia, potrero activo, uuid de otra recorrida) son del
 * SincronizadorRecorridas. Los nombres son los de las columnas.
 */
class SubirRecorridaRequest extends FormRequest
{
    public const MAX_MEDICIONES = 1000;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'potrero_id' => ['required', 'integer'],
            'iniciada_en' => ['required', 'date'],
            'dispositivo' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'mediciones' => ['present', 'array', 'max:'.self::MAX_MEDICIONES],
            'mediciones.*.uuid' => ['required', 'uuid', 'distinct'],
            'mediciones.*.tomada_en' => ['required', 'date'],
            'mediciones.*.altura_cm' => ['required', 'numeric', 'between:0,999.9'],
            'mediciones.*.distancia_cm' => ['nullable', 'numeric', 'between:0,999.9'],
            'mediciones.*.kg_ms_ha' => ['nullable', 'integer', 'between:0,4294967295'],
            'mediciones.*.latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:mediciones.*.longitud'],
            'mediciones.*.longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:mediciones.*.latitud'],
            'mediciones.*.precision_m' => ['nullable', 'numeric', 'between:0,99999.9'],
            'mediciones.*.inclinacion_grados' => ['nullable', 'numeric', 'between:-180,180'],
            'mediciones.*.imagenes' => ['required', 'integer', 'between:0,255'],
            'mediciones.*.dispersion_cm' => ['nullable', 'numeric', 'between:0,999.99'],
            'mediciones.*.calibracion' => ['nullable', 'array'],
        ];
    }
}
