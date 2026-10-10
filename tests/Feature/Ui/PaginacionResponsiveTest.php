<?php

namespace Tests\Feature\Ui;

use App\Livewire\Traits\PaginacionResponsive;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithPagination;
use Tests\TestCase;

class PaginacionResponsiveTest extends TestCase
{
    private function componenteManual(): Component
    {
        return new class extends Component
        {
            use PaginacionResponsive;

            public int $currentPage = 3;

            public function mount(): void
            {
                $this->checkIfMobile();
            }

            public function render(): string
            {
                return '<div {{ $this->atributosPaginacionResponsive() }}>x</div>';
            }
        };
    }

    public function test_por_defecto_es_escritorio_con_10_por_pagina(): void
    {
        Livewire::test($this->componenteManual())
            ->assertSet('perPage', 10)
            ->assertSet('esMobile', false);
    }

    public function test_detecta_mobile_por_user_agent_en_mount(): void
    {
        Livewire::withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'])
            ->test($this->componenteManual())
            ->assertSet('perPage', 5)
            ->assertSet('esMobile', true);
    }

    public function test_updatePerPage_cambia_la_cantidad_y_vuelve_a_la_primera_pagina(): void
    {
        Livewire::test($this->componenteManual())
            ->assertSet('currentPage', 3)
            ->call('updatePerPage', true)
            ->assertSet('perPage', 5)
            ->assertSet('esMobile', true)
            ->assertSet('currentPage', 1)
            ->set('currentPage', 2)
            ->call('updatePerPage', false)
            ->assertSet('perPage', 10)
            ->assertSet('currentPage', 1);
    }

    public function test_updatePerPage_sin_cambio_no_toca_la_pagina(): void
    {
        Livewire::test($this->componenteManual())
            ->call('updatePerPage', false)
            ->assertSet('perPage', 10)
            ->assertSet('currentPage', 3);
    }

    public function test_con_WithPagination_usa_resetPage(): void
    {
        $componente = new class extends Component
        {
            use PaginacionResponsive, WithPagination;

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::withQueryParams(['page' => 4])
            ->test($componente)
            ->call('updatePerPage', true)
            ->assertSet('perPage', 5)
            ->assertSet('paginators.page', 1);
    }

    public function test_el_wrapper_llama_updatePerPage_al_cargar_y_en_el_resize_con_breakpoint_768(): void
    {
        $html = Livewire::test($this->componenteManual())->html();

        $this->assertStringContainsString('x-data="{ mobile: window.innerWidth < 768 }"', $html);
        $this->assertStringContainsString('x-init="if (mobile !== false) $wire.updatePerPage(mobile)"', $html);
        $this->assertStringContainsString('x-on:resize.window.debounce.250ms=', $html);
        $this->assertStringContainsString('if (m !== mobile) { mobile = m; $wire.updatePerPage(m) }', $html);
    }
}
