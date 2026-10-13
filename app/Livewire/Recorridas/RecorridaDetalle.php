<?php

namespace App\Livewire\Recorridas;

use App\Livewire\Traits\EnviaToasts;
use App\Models\EstadoFenologico;
use App\Models\Medicion;
use App\Models\Recorrida;
use App\Support\Recorridas\ExportadorRecorridas;
use App\Support\Recorridas\ResumenRecorrida;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La ficha de una recorrida: resumen, mapa de los puntos coloreados por
 * altura y la tabla de puntos. Se monta abierta desde Recorridas (D57) y
 * avisa con `cerrar-recorrida-detalle` y `recorrida-actualizada`.
 *
 * Cada acción pide Editar en Recorridas y vuelve a buscar la recorrida con
 * visiblesPara(): un recorredor no toca las de otro aunque mande el id.
 * Cambiar el estado fenológico (si el recorredor eligió mal) cambia la curva
 * con la que se calculan los kg (P23).
 */
class RecorridaDetalle extends Component
{
    use EnviaToasts;

    public int $recorridaId;

    public bool $confirmandoAnular = false;

    public int $z = 50;

    /** El estado fenológico de la recorrida (P23), editable desde la ficha. */
    public ?int $estadoId = null;

    public function mount(int $recorridaId, int $z = 50): void
    {
        $this->recorridaId = $recorridaId;
        $this->z = $z;
        $this->estadoId = $this->recorrida()->estado_fenologico_id; // 404 si no es visible para este usuario
    }

    // ----- Estado fenológico (P23) -------------------------------------------

    public function updatedEstadoId(mixed $valor): void
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $recorrida = $this->recorrida();
        $vacio = $valor === null || $valor === '';
        $estado = $vacio ? null : $this->estadosElegibles($recorrida)->find((int) $valor);

        if (! $vacio && $estado === null) {
            $this->estadoId = $recorrida->estado_fenologico_id;

            return;
        }

        $recorrida->update(['estado_fenologico_id' => $estado?->id]);
        $this->estadoId = $estado?->id;
        $this->toastExito($recorrida->curvaBiomasa() !== null
            ? __('recorridas.toasts.estado_con_curva')
            : __('recorridas.toasts.estado_sin_curva'));
        $this->dispatch('recorrida-actualizada');
    }

    /**
     * Los estados activos más el que la recorrida ya tenía, aunque esté
     * desactivado (se conserva).
     *
     * @return Collection<int, EstadoFenologico>
     */
    private function estadosElegibles(Recorrida $recorrida): Collection
    {
        $actual = $recorrida->estado_fenologico_id;

        return EstadoFenologico::query()
            ->where(fn ($q) => $q->where('activo', true)->when($actual !== null, fn ($q) => $q->orWhere('id', $actual)))
            ->orderBy('id')
            ->get(['id', 'nombre']);
    }

    public function cerrar(): void
    {
        $this->dispatch('cerrar-recorrida-detalle');
    }

    // ----- Puntos ------------------------------------------------------------

    public function descartarPunto(int $id): void
    {
        $this->cambiarPunto($id, false);
        $this->toastAviso(__('recorridas.toasts.descartado'));
    }

    public function restaurarPunto(int $id): void
    {
        $this->cambiarPunto($id, true);
        $this->toastExito(__('recorridas.toasts.restaurado'));
    }

    private function cambiarPunto(int $id, bool $activo): void
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $this->recorrida()->mediciones()->findOrFail($id)->update(['activo' => $activo]);
        $this->dispatch('recorrida-actualizada');
    }

    // ----- Anular / restaurar la recorrida -----------------------------------

    public function confirmarAnular(): void
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $this->confirmandoAnular = true;
    }

    public function cancelarAnular(): void
    {
        $this->confirmandoAnular = false;
    }

    public function anular(): void
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $this->recorrida()->update(['activo' => false]);
        $this->confirmandoAnular = false;
        $this->toastAviso(__('recorridas.toasts.anulada'));
        $this->dispatch('recorrida-actualizada');
    }

    public function restaurar(): void
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $this->recorrida()->update(['activo' => true]);
        $this->toastExito(__('recorridas.toasts.restaurada'));
        $this->dispatch('recorrida-actualizada');
    }

    // ----- Exportar ----------------------------------------------------------

    public function exportar(): StreamedResponse
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $recorrida = $this->recorrida();
        $csv = ExportadorRecorridas::csv(Recorrida::query()->whereKey($recorrida->id));

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            ExportadorRecorridas::nombreArchivoDe($recorrida),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    // ----- Render ------------------------------------------------------------

    public function render()
    {
        $recorrida = $this->recorrida()->load([
            'potrero.campo',
            'estadoFenologico',
            'user',
            'mediciones' => fn ($q) => $q->orderBy('tomada_en')->orderBy('id'),
        ]);

        $recta = $recorrida->recta();
        $resumen = ResumenRecorrida::de($recorrida->mediciones, $recta);

        return view('livewire.recorridas.recorrida-detalle', [
            'recorrida' => $recorrida,
            'recta' => $recta,
            'resumen' => $resumen,
            'estados' => $this->estadosElegibles($recorrida),
            'verCurvas' => Gate::allows('puedeVer', 'curvas'),
            'puedeEditar' => Gate::allows('puedeEditar', 'recorridas'),
            'mapa' => $this->datosMapa($recorrida, $resumen),
        ]);
    }

    /**
     * Lo que recibe el componente Alpine mapaVista: el contorno del potrero y
     * los puntos con GPS, coloreados con la escala de los puntos activos.
     *
     * @return array{potreros: list<array>, puntos: list<array>, escala: ?array}|null null si ningún punto tiene ubicación
     */
    private function datosMapa(Recorrida $recorrida, ResumenRecorrida $resumen): ?array
    {
        $puntos = $recorrida->mediciones
            ->filter(fn (Medicion $m) => $m->tieneUbicacion())
            ->map(fn (Medicion $m) => [
                'lat' => $m->latitud,
                'lon' => $m->longitud,
                'altura' => $m->altura_cm,
                'activo' => $m->activo,
                'etiqueta' => $m->tomada_en->format('H:i').' · '.__('recorridas.cm', ['valor' => number_format($m->altura_cm, 1, ',', '.')])
                    .($m->activo ? '' : ' · '.__('recorridas.detalle.descartado')),
            ])
            ->values()
            ->all();

        if ($puntos === []) {
            return null;
        }

        $potrero = $recorrida->potrero;

        return [
            'potreros' => $potrero->contorno === null ? [] : [['nombre' => $potrero->nombre, 'contorno' => $potrero->contorno, 'resaltado' => true]],
            'puntos' => $puntos,
            'escala' => $resumen->puntos > 0 ? ['min' => $resumen->alturaMinima, 'max' => $resumen->alturaMaxima] : null,
        ];
    }

    private function recorrida(): Recorrida
    {
        return Recorrida::query()->visiblesPara(auth()->user())->findOrFail($this->recorridaId);
    }
}
