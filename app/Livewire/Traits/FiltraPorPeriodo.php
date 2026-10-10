<?php

namespace App\Livewire\Traits;

use Illuminate\Support\Carbon;

/**
 * El período de un listado (`desde` / `hasta`, en la URL) y sus atajos:
 * hoy, esta semana, este mes. Lo usan Caja (D47) y Auditoría (D64).
 *
 * El componente declara las dos propiedades con su `#[Url]` y llama
 * `normalizarPeriodo()` en `mount()`. Las fechas inválidas caen en hoy; si
 * quedan cruzadas, la que se tocó manda. Cada cambio vuelve a la primera
 * página.
 */
trait FiltraPorPeriodo
{
    public function updatedDesde(): void
    {
        $this->normalizarPeriodo();
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->normalizarPeriodo(mandaHasta: true);
        $this->resetPage();
    }

    public function hoy(): void
    {
        $this->periodo(Carbon::today(), Carbon::today());
    }

    public function estaSemana(): void
    {
        $this->periodo(Carbon::today()->startOfWeek(), Carbon::today());
    }

    public function esteMes(): void
    {
        $this->periodo(Carbon::today()->startOfMonth(), Carbon::today());
    }

    private function periodo(Carbon $desde, Carbon $hasta): void
    {
        $this->desde = $desde->toDateString();
        $this->hasta = $hasta->toDateString();
        $this->resetPage();
    }

    /** Fechas inválidas caen en hoy; si quedan cruzadas, la que se tocó manda. */
    private function normalizarPeriodo(bool $mandaHasta = false): void
    {
        $hoy = Carbon::today()->toDateString();
        $this->desde = self::fechaValida($this->desde) ? $this->desde : $hoy;
        $this->hasta = self::fechaValida($this->hasta) ? $this->hasta : $hoy;

        if ($this->desde > $this->hasta) {
            $mandaHasta ? $this->desde = $this->hasta : $this->hasta = $this->desde;
        }
    }

    private static function fechaValida(string $fecha): bool
    {
        return $fecha !== '' && Carbon::hasFormat($fecha, 'Y-m-d');
    }
}
