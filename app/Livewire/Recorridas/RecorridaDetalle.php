<?php

namespace App\Livewire\Recorridas;

use App\Livewire\Traits\EnviaToasts;
use App\Models\Medicion;
use App\Models\Recorrida;
use App\Support\Recorridas\ExportadorRecorridas;
use App\Support\Recorridas\ResumenRecorrida;
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
 */
class RecorridaDetalle extends Component
{
    use EnviaToasts;

    public int $recorridaId;

    public bool $confirmandoAnular = false;

    public int $z = 50;

    public function mount(int $recorridaId, int $z = 50): void
    {
        $this->recorridaId = $recorridaId;
        $this->z = $z;
        $this->recorrida(); // 404 si no es visible para este usuario
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
            'user',
            'mediciones' => fn ($q) => $q->orderBy('tomada_en')->orderBy('id'),
        ]);

        $resumen = ResumenRecorrida::de($recorrida->mediciones);

        return view('livewire.recorridas.recorrida-detalle', [
            'recorrida' => $recorrida,
            'resumen' => $resumen,
            'puedeEditar' => Gate::allows('puedeEditar', 'recorridas'),
            'mapa' => $this->datosMapa($recorrida, $resumen),
        ]);
    }

    /**
     * Lo que recibe el componente Alpine mapaVista: el contorno del potrero y
     * los puntos con GPS, coloreados con la escala de los puntos activos.
     *
     * @return array{potreros: list<array>, puntos: list<array>, escala: ?array}|null  null si ningún punto tiene ubicación
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
