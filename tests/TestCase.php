<?php

namespace Tests;

use App\Support\Tema;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tema cachea la paleta en un static; cada test arranca sin ese estado.
        Tema::reiniciar();
    }
}
