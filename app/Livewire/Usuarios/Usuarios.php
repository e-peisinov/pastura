<?php

namespace App\Livewire\Usuarios;

use App\Enums\Nivel;
use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Livewire\Traits\OrdenaPorColumna;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Usuarios (D49): el listado con la baja lógica, la reactivación y el cambio
 * de contraseña. El alta y la edición, con la matriz de permisos, van en el
 * hijo UsuarioFormulario.
 *
 * Guardas: nadie se da de baja a sí mismo, y el sistema nunca queda sin un
 * usuario activo con Editar en Usuarios. Un usuario dado de baja pierde la
 * sesión (UsuarioActivo) y sus órdenes y pagos conservan quién los hizo.
 */
#[Layout('layouts.app')]
class Usuarios extends Component
{
    use ConfirmaDescarte, EnviaToasts, OrdenaPorColumna;

    public const MODULO = User::MODULO_USUARIOS;

    /** activos | inactivos | null (todos). */
    public string $filtroEstado = 'activos';

    /** Usuario abierto en el formulario: null = cerrado, 0 = alta, id = edición. */
    public ?int $formulario = null;

    /** Usuario al que se le cambia la contraseña. */
    public ?int $contrasenaId = null;

    public string $contrasena = '';

    public string $contrasena_confirmation = '';

    public ?int $bajaId = null;

    // ----- Orden (D55) ------------------------------------------------------

    protected function columnasOrdenables(): array
    {
        return [
            // Los activos siempre arriba; dentro de cada grupo, por nombre.
            'usuario' => fn (Builder $q, string $dir) => $q->orderByDesc('activo')->orderBy('name', $dir),
            'rol' => 'rol',
            'estado' => 'activo',
        ];
    }

    protected function ordenInicial(): array
    {
        return ['usuario', 'asc'];
    }

    // ----- Alta / edición (modal hijo) -------------------------------------

    public function nuevo(): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        $this->formulario = 0;
    }

    public function editar(int $id): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        $this->formulario = User::query()->findOrFail($id)->id;
    }

    #[On('cerrar-usuario-formulario')]
    public function cerrarUsuarioFormulario(): void
    {
        $this->formulario = null;
    }

    // ----- Contraseña -------------------------------------------------------

    public function abrirContrasena(int $id): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        $this->contrasena = $this->contrasena_confirmation = '';
        $this->resetValidation();
        $this->contrasenaId = User::query()->findOrFail($id)->id;
        $this->marcarFormularioLimpio();
    }

    public function guardarContrasena(): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        $usuario = User::query()->findOrFail($this->contrasenaId);

        $this->validate([
            'contrasena' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $usuario->update(['password' => $this->contrasena]);

        $this->toastExito(__('usuarios.toasts.contrasena'));
        $this->cerrarFormulario();
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['contrasena', 'contrasena_confirmation'];
    }

    public function cerrarFormulario(): void
    {
        $this->contrasenaId = null;
        $this->contrasena = $this->contrasena_confirmation = '';
        $this->resetValidation();
    }

    // ----- Baja lógica y reactivación --------------------------------------

    public function confirmarBaja(int $id): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        if ($id === auth()->id()) {
            $this->toastAviso(__('usuarios.toasts.a_si_mismo'));

            return;
        }

        $this->bajaId = User::query()->findOrFail($id)->id;
    }

    public function cancelarBaja(): void
    {
        $this->bajaId = null;
    }

    public function baja(): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        $usuario = User::query()->findOrFail($this->bajaId);
        $this->bajaId = null;

        if ($usuario->is(auth()->user())) {
            $this->toastAviso(__('usuarios.toasts.a_si_mismo'));

            return;
        }

        if (! User::otroPuedeEditarUsuarios($usuario->id)) {
            $this->toastError(__('usuarios.toasts.ultimo_editor'));

            return;
        }

        $usuario->update(['activo' => false]);
        $this->toastAviso(__('usuarios.toasts.desactivado'));
    }

    public function reactivar(int $id): void
    {
        Gate::authorize('puedeEditar', self::MODULO);

        User::query()->findOrFail($id)->update(['activo' => true]);
        $this->toastExito(__('usuarios.toasts.reactivado'));
    }

    // ----- Render -----------------------------------------------------------

    public function render()
    {
        $usuarios = $this->aplicarOrden(User::query()
            ->with('permisos')
            ->when($this->filtroEstado === 'activos', fn (Builder $q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivos', fn (Builder $q) => $q->where('activo', false)))
            ->get();

        return view('livewire.usuarios.usuarios', [
            'usuarios' => $usuarios,
            'resumenes' => $usuarios->mapWithKeys(fn (User $u) => [$u->id => $this->resumen($u)]),
            'puedeEditar' => Gate::allows('puedeEditar', self::MODULO),
            'yoId' => auth()->id(),
            'hayFiltro' => $this->filtroEstado !== 'activos',
            // Combo poblado en render (§4c).
            'estados' => [
                'todos' => __('ui.filtros.todos'),
                'activos' => __('ui.filtros.activos'),
                'inactivos' => __('ui.filtros.inactivos'),
            ],
            'usuarioContrasena' => $this->contrasenaId !== null ? User::query()->find($this->contrasenaId) : null,
            'usuarioEnBaja' => $this->bajaId !== null ? User::query()->find($this->bajaId) : null,
        ]);
    }

    /** "Edita 12 módulos · ve 2 · sin acceso a 1": la matriz en una línea. */
    private function resumen(User $usuario): string
    {
        $conteo = ['editar' => 0, 'ver' => 0, 'sin_acceso' => 0];

        foreach ($usuario->niveles() as $nivel) {
            $conteo[$nivel->value]++;
        }

        $partes = [trans_choice('usuarios.resumen.editar', $conteo[Nivel::Editar->value], ['count' => $conteo[Nivel::Editar->value]])];

        foreach ([Nivel::Ver, Nivel::SinAcceso] as $nivel) {
            if ($conteo[$nivel->value] > 0) {
                $partes[] = trans_choice('usuarios.resumen.'.$nivel->value, $conteo[$nivel->value], ['count' => $conteo[$nivel->value]]);
            }
        }

        return implode(' · ', $partes);
    }
}
