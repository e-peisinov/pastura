<?php

namespace App\Livewire\Traits;

use Illuminate\Support\HtmlString;

/**
 * Cantidad por página responsiva (estandares-ux.md §5): 5 en móvil, 10 en
 * escritorio, con el breakpoint en 768 px (md de Tailwind).
 *
 * Uso:
 *   - `$this->checkIfMobile()` en `mount()`: primera estimación por user agent,
 *     para que el primer render ya venga con la cantidad correcta.
 *   - El wrapper del listado imprime `{{ $this->atributosPaginacionResponsive() }}`:
 *     ese x-data corrige la estimación al cargar y llama `updatePerPage(isMobile)`
 *     en el resize, solo cuando se cruza el breakpoint.
 *
 * No copiar este par de métodos dentro de cada componente.
 */
trait PaginacionResponsive
{
    public const POR_PAGINA_MOBILE = 5;

    public const POR_PAGINA_ESCRITORIO = 10;

    public const BREAKPOINT_MOBILE = 768;

    public int $perPage = self::POR_PAGINA_ESCRITORIO;

    public bool $esMobile = false;

    /** Estimación inicial por user agent; el navegador la corrige al cargar. */
    public function checkIfMobile(): void
    {
        $userAgent = request()->userAgent() ?? '';

        $this->aplicarPerPage((bool) preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone/i', $userAgent));
    }

    /** La llama el navegador (x-data del wrapper) al cargar y al cruzar el breakpoint. */
    public function updatePerPage(bool $isMobile): void
    {
        $cambio = $this->aplicarPerPage($isMobile);

        if ($cambio) {
            $this->volverAPrimeraPagina();
        }
    }

    /** Atributos Alpine del wrapper del listado. */
    public function atributosPaginacionResponsive(): HtmlString
    {
        $breakpoint = self::BREAKPOINT_MOBILE;
        $estimado = $this->esMobile ? 'true' : 'false';

        return new HtmlString(
            'x-data="{ mobile: window.innerWidth < '.$breakpoint.' }" '
            .'x-init="if (mobile !== '.$estimado.') $wire.updatePerPage(mobile)" '
            .'x-on:resize.window.debounce.250ms="const m = window.innerWidth < '.$breakpoint.'; if (m !== mobile) { mobile = m; $wire.updatePerPage(m) }"'
        );
    }

    /** @return bool si la cantidad por página cambió */
    private function aplicarPerPage(bool $esMobile): bool
    {
        $anterior = $this->perPage;

        $this->esMobile = $esMobile;
        $this->perPage = $esMobile ? self::POR_PAGINA_MOBILE : self::POR_PAGINA_ESCRITORIO;

        return $anterior !== $this->perPage;
    }

    private function volverAPrimeraPagina(): void
    {
        if (method_exists($this, 'resetPage')) {
            // WithPagination de Livewire.
            $this->resetPage();
        } elseif (property_exists($this, 'currentPage')) {
            // Paginación manual con <x-ui.paginacion>.
            $this->currentPage = 1;
        }
    }
}
