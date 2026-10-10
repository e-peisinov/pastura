<?php

namespace App\Models;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Support\Modulos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** El módulo cuya edición nunca puede quedar sin un usuario activo (D49). */
    public const MODULO_USUARIOS = 'usuarios';

    /**
     * Un usuario nace activo también en memoria (antes del primer refresh):
     * los Gates y el middleware leen `activo` del modelo.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'activo' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }

    // ----- Rol y permisos (D49) ---------------------------------------------

    /**
     * El rol ya no es un permiso (D49): sigue diciendo de quién es cada cosa
     * (las recorridas que hizo en el campo).
     */
    public function esRecorredor(): bool
    {
        return $this->rol === Rol::Recorredor;
    }

    /** Una fila por módulo con nivel propio; sin fila, vale el del rol. */
    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class);
    }

    /** El nivel efectivo sobre un módulo: la fila del usuario o el default del rol. */
    public function nivel(string $modulo): Nivel
    {
        $propio = $this->permisos->firstWhere('modulo', $modulo);

        if ($propio !== null) {
            return $propio->nivel;
        }

        return Modulos::porNombre($modulo)?->porOmision($this->rol) ?? Nivel::SinAcceso;
    }

    /**
     * La matriz completa del usuario, en el orden del sidebar.
     *
     * @return array<string, Nivel>
     */
    public function niveles(): array
    {
        $niveles = [];

        foreach (Modulos::nombres() as $modulo) {
            $niveles[$modulo] = $this->nivel($modulo);
        }

        return $niveles;
    }

    public function puedeVer(string $modulo): bool
    {
        return $this->activo && $this->nivel($modulo)->puedeVer();
    }

    public function puedeEditar(string $modulo): bool
    {
        return $this->activo && $this->nivel($modulo)->puedeEditar();
    }

    public function asignarNivel(string $modulo, Nivel $nivel): void
    {
        $this->permisos()->updateOrCreate(['modulo' => $modulo], ['nivel' => $nivel]);
        $this->unsetRelation('permisos');
    }

    /**
     * Guarda la matriz entera (D49): una fila por módulo, así el nivel queda
     * escrito aunque después cambie el rol o el default de un módulo.
     *
     * @param  array<string, Nivel>  $niveles
     */
    public function asignarNiveles(array $niveles): void
    {
        foreach ($niveles as $modulo => $nivel) {
            $this->permisos()->updateOrCreate(['modulo' => $modulo], ['nivel' => $nivel]);
        }

        $this->unsetRelation('permisos');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Usuarios cuyo nivel efectivo sobre el módulo es Editar: fila propia, o sin fila y rol con Editar por omisión. */
    public function scopeConEditarEn(Builder $query, string $modulo): Builder
    {
        $rolesConEditar = array_values(array_filter(
            Rol::cases(),
            fn (Rol $rol) => Modulos::porNombre($modulo)?->porOmision($rol) === Nivel::Editar,
        ));

        return $query->where(function (Builder $q) use ($modulo, $rolesConEditar) {
            $q->whereHas('permisos', fn (Builder $p) => $p->where('modulo', $modulo)->where('nivel', Nivel::Editar->value));

            if ($rolesConEditar !== []) {
                $q->orWhere(function (Builder $sinFila) use ($modulo, $rolesConEditar) {
                    $sinFila->whereIn('rol', array_map(fn (Rol $rol) => $rol->value, $rolesConEditar))
                        ->whereDoesntHave('permisos', fn (Builder $p) => $p->where('modulo', $modulo));
                });
            }
        });
    }

    /**
     * La guarda de D49: ¿queda algún OTRO usuario activo con Editar en
     * Usuarios si a este se lo desactiva o se lo recorta?
     */
    public static function otroPuedeEditarUsuarios(?int $exceptoId): bool
    {
        return static::query()
            ->activos()
            ->conEditarEn(self::MODULO_USUARIOS)
            ->when($exceptoId !== null, fn (Builder $q) => $q->whereKeyNot($exceptoId))
            ->exists();
    }

    // ----- Relaciones --------------------------------------------------------

    /** Recorridas que hizo con la app de campo. */
    public function recorridas(): HasMany
    {
        return $this->hasMany(Recorrida::class);
    }
}
