<?php

namespace App\Livewire\Traits;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ordenar un listado clickeando la cabecera de la columna (estandares-ux.md
 * §5, D55). Primer click asc, segundo desc, sobre una columna distinta vuelve
 * a asc. La cabecera se dibuja con <x-ui.th columna="..." :orden="$this->orden()">.
 *
 * El componente define:
 *   - `columnasOrdenables(): array` — clave de la cabecera => cómo se ordena:
 *       'nombre'                      una columna;
 *       ['apellido', 'nombre']        varias, todas en la misma dirección;
 *       fn (Builder $q, string $dir)  lo que haga falta (raw, relación, etc.).
 *   - `ordenInicial(): array` — [clave, 'asc'|'desc'] con el que abre el listado.
 *
 * Y en la consulta del render llama `$this->aplicarOrden($query)`, que
 * agrega siempre el id como desempate para que la paginación sea estable.
 * Las claves que no están en el mapa se ignoran: nada de ordenar por lo que
 * mande el navegador.
 */
trait OrdenaPorColumna
{
    public ?string $ordenColumna = null;

    public string $ordenDireccion = 'asc';

    /** @return array<string, string|list<string>|Closure(Builder, string): void> */
    abstract protected function columnasOrdenables(): array;

    /** @return array{0: string, 1: string} [clave, 'asc'|'desc'] */
    abstract protected function ordenInicial(): array;

    /** Hook de Livewire para el trait: fija el orden con el que abre el listado. */
    public function mountOrdenaPorColumna(): void
    {
        [$this->ordenColumna, $this->ordenDireccion] = $this->ordenInicial();
    }

    /** La llama <x-ui.th> desde la cabecera. */
    public function ordenarPor(string $columna): void
    {
        if (! array_key_exists($columna, $this->columnasOrdenables())) {
            return;
        }

        if ($this->ordenColumna === $columna) {
            $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenColumna = $columna;
            $this->ordenDireccion = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Lo que recibe <x-ui.th :orden="..."> para pintar la flecha. */
    public function orden(): array
    {
        return ['columna' => $this->ordenColumna, 'direccion' => $this->ordenDireccion];
    }

    protected function aplicarOrden(Builder $query): Builder
    {
        $columnas = $this->columnasOrdenables();
        $clave = $this->ordenColumna !== null && array_key_exists($this->ordenColumna, $columnas)
            ? $this->ordenColumna
            : $this->ordenInicial()[0];
        $direccion = $this->ordenDireccion === 'desc' ? 'desc' : 'asc';

        $como = $columnas[$clave];

        if ($como instanceof Closure) {
            $como($query, $direccion);
        } else {
            foreach ((array) $como as $columna) {
                $query->orderBy($columna, $direccion);
            }
        }

        // Desempate estable: dos filas iguales en la columna no cambian de
        // página entre una request y la siguiente.
        return $query->orderBy($query->getModel()->getQualifiedKeyName(), $direccion);
    }
}
