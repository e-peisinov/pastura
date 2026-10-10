<?php

namespace App\Livewire\Usuarios;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\User;
use App\Support\Modulo;
use App\Support\Modulos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Alta y edición de un usuario (hijo de Usuarios, D49): nombre, correo, rol,
 * contraseña (solo en el alta) y la matriz de permisos por módulo en el
 * mismo modal.
 *
 * El rol carga la matriz por omisión (Modulos::nivelesPorOmision) y lo que se
 * guarda es la matriz, fila por módulo. Los usuarios nacen con el mail
 * verificado: las rutas exigen `verified` y el demo no manda mails.
 */
class UsuarioFormulario extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    public ?int $usuarioId = null;

    public string $nombre = '';

    public string $email = '';

    public string $rol = Rol::Recorredor->value;

    public string $contrasena = '';

    public string $contrasena_confirmation = '';

    /** @var array<string, string> módulo => valor de Nivel */
    public array $permisos = [];

    public function mount(?int $usuarioId = null): void
    {
        Gate::authorize('puedeEditar', User::MODULO_USUARIOS);

        $this->usuarioId = $usuarioId;

        if ($usuarioId !== null) {
            $usuario = User::query()->with('permisos')->findOrFail($usuarioId);

            $this->nombre = $usuario->name;
            $this->email = $usuario->email;
            $this->rol = $usuario->rol->value;
            $this->permisos = array_map(fn (Nivel $nivel) => $nivel->value, $usuario->niveles());
        } else {
            $this->cargarPermisosDelRol();
        }

        $this->marcarFormularioLimpio();
    }

    /** Cambiar el rol vuelve a cargar la matriz por omisión de ese rol; después se ajusta. */
    public function updatedRol(): void
    {
        $this->cargarPermisosDelRol();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', User::MODULO_USUARIOS);

        $usuario = $this->usuarioId !== null ? User::query()->findOrFail($this->usuarioId) : null;

        $reglas = [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->usuarioId)],
            'rol' => ['required', Rule::enum(Rol::class)],
            'permisos' => ['required', 'array'],
            'permisos.*' => ['required', Rule::enum(Nivel::class)],
        ];

        if ($usuario === null) {
            $reglas['contrasena'] = ['required', 'string', 'confirmed', Password::min(8)];
        }

        $datos = $this->validate($reglas);

        $niveles = $this->nivelesElegidos();

        // La guarda de D49: nunca sin un usuario activo con Editar en Usuarios.
        if ($usuario !== null && $usuario->activo
            && $niveles[User::MODULO_USUARIOS] !== Nivel::Editar
            && ! User::otroPuedeEditarUsuarios($usuario->id)) {
            $this->addError('permisos.'.User::MODULO_USUARIOS, __('usuarios.validacion.ultimo_editor'));
            $this->toastError(__('usuarios.validacion.ultimo_editor'));

            return;
        }

        $atributos = [
            'name' => trim($datos['nombre']),
            'email' => mb_strtolower(trim($datos['email'])),
            'rol' => Rol::from($datos['rol']),
        ];

        if ($usuario === null) {
            $usuario = User::query()->create($atributos + ['password' => $this->contrasena, 'activo' => true]);
            // Nace verificado: las rutas exigen `verified` y el demo no manda mails.
            $usuario->markEmailAsVerified();
            $usuario->asignarNiveles($niveles);
            $this->toastExito(__('usuarios.toasts.creado'));
        } else {
            $usuario->update($atributos);
            $usuario->asignarNiveles($niveles);
            $this->toastExito(__('usuarios.toasts.modificado'));
        }

        $this->marcarFormularioLimpio();
        $this->dispatch('usuario-guardado');
        $this->cerrarFormulario();
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['nombre', 'email', 'rol', 'contrasena', 'contrasena_confirmation', 'permisos'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-usuario-formulario');
    }

    private function cargarPermisosDelRol(): void
    {
        $rol = Rol::tryFrom($this->rol) ?? Rol::Recorredor;

        $this->permisos = array_map(fn (Nivel $nivel) => $nivel->value, Modulos::nivelesPorOmision($rol));
    }

    /**
     * La matriz completa a guardar: cada módulo del sistema, aunque la vista
     * solo muestre los que ya tienen ruta.
     *
     * @return array<string, Nivel>
     */
    private function nivelesElegidos(): array
    {
        $porOmision = Modulos::nivelesPorOmision(Rol::tryFrom($this->rol) ?? Rol::Recorredor);
        $niveles = [];

        foreach (Modulos::nombres() as $modulo) {
            $niveles[$modulo] = Nivel::tryFrom($this->permisos[$modulo] ?? '') ?? $porOmision[$modulo];
        }

        return $niveles;
    }

    public function render()
    {
        return view('livewire.usuarios.usuario-formulario', [
            // Combos poblados en render (§4c).
            'roles' => Rol::cases(),
            'niveles' => Nivel::cases(),
            // Solo los módulos que ya tienen pantalla, como el sidebar.
            'modulos' => array_values(array_filter(Modulos::todos(), fn (Modulo $m) => $m->tieneRuta())),
        ]);
    }
}
