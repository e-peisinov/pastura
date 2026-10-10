<?php

namespace App\Livewire\Configuracion;

use App\Enums\ColorAcento;
use App\Livewire\Traits\ConfirmaDescarte;
use App\Models\Configuracion;
use App\Support\Tema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Datos de la organización (hijo de Configuracion, D8 y P17): nombre, logo y
 * color de acento. Es la marca blanca.
 *
 * Al guardar se recarga la pantalla (redirect con navigate): el nombre, el
 * logo y el color viven en el layout, que una acción Livewire no vuelve a
 * dibujar. El aviso de éxito viaja por flash de sesión (§3).
 */
class DatosOrganizacion extends Component
{
    use ConfirmaDescarte {
        formularioSucio as private formularioSucioBase;
    }
    use WithFileUploads;

    public const Z_DESCARTE = 60;

    public const DISCO = 'public';

    public const CARPETA = 'logos';

    public string $nombre = '';

    public string $color_acento = 'green';

    public ?string $logo_actual = null;

    /** Logo nuevo, todavía sin guardar. */
    public ?TemporaryUploadedFile $logo = null;

    public bool $quitar_logo = false;

    public function mount(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $configuracion = Configuracion::instancia();

        $this->nombre = $configuracion->nombre ?? '';
        $this->color_acento = $configuracion->color_acento?->value ?? ColorAcento::Green->value;
        $this->logo_actual = $configuracion->logo_path;

        $this->marcarFormularioLimpio();
    }

    /** El archivo se valida apenas se elige, para avisar antes de guardar. */
    public function updatedLogo(): void
    {
        try {
            $this->validate(['logo' => $this->reglaLogo()], ['logo' => __('configuracion.organizacion.validacion.logo')]);
        } catch (ValidationException $e) {
            // Un archivo rechazado no se queda como "logo nuevo": ni se previsualiza ni se guarda.
            $this->logo = null;

            throw $e;
        }

        $this->quitar_logo = false;
    }

    public function sacarLogo(): void
    {
        $this->logo = null;
        $this->quitar_logo = true;
        $this->resetValidation('logo');
    }

    public function elegirPaleta(string $paleta): void
    {
        if (ColorAcento::tryFrom($paleta) !== null) {
            $this->color_acento = $paleta;
        }
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'color_acento' => ['required', Rule::enum(ColorAcento::class)],
            'logo' => $this->reglaLogo(),
        ], ['logo' => __('configuracion.organizacion.validacion.logo')]);

        $configuracion = Configuracion::instancia();
        $configuracion->fill([
            'nombre' => trim($datos['nombre']),
            'color_acento' => ColorAcento::from($datos['color_acento']),
        ]);

        $logoAnterior = $configuracion->logo_path;

        if ($this->logo !== null) {
            $configuracion->logo_path = $this->logo->store(self::CARPETA, self::DISCO);
        } elseif ($this->quitar_logo) {
            $configuracion->logo_path = null;
        }

        $configuracion->save();

        if ($logoAnterior !== null && $logoAnterior !== $configuracion->logo_path) {
            Storage::disk(self::DISCO)->delete($logoAnterior);
        }

        Tema::reiniciar();
        $this->marcarFormularioLimpio();

        // El layout (sidebar, título, acento) no se redibuja en una acción:
        // se recarga la pantalla y el aviso viaja por flash (§3).
        session()->flash('success', __('configuracion.organizacion.toasts.guardados'));
        $this->redirect(route('configuracion'), navigate: true);
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['nombre', 'color_acento', 'quitar_logo'];
    }

    /** Un logo elegido y no guardado también es un cambio pendiente. */
    public function formularioSucio(): bool
    {
        return $this->logo !== null || $this->formularioSucioBase();
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-datos-organizacion');
    }

    /** @return list<string> */
    private function reglaLogo(): array
    {
        return ['nullable', 'image', 'max:1024'];
    }

    public function render()
    {
        return view('livewire.configuracion.datos-organizacion', [
            'paletas' => Tema::paletasDisponibles(),
            'urlLogoActual' => $this->logo_actual !== null && ! $this->quitar_logo
                ? asset('storage/'.$this->logo_actual)
                : null,
        ]);
    }
}
