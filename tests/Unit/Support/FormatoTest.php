<?php

namespace Tests\Unit\Support;

use App\Support\Formato;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FormatoTest extends TestCase
{
    public function test_moneda_en_formato_argentino(): void
    {
        $this->assertSame('$ 1.234,56', Formato::moneda(1234.56));
        $this->assertSame('$ 0,00', Formato::moneda(null));
        $this->assertSame('$ 15.000,00', Formato::moneda('15000'));
        $this->assertSame('$ -500,00', Formato::moneda(-500));
    }

    public function test_numero_y_cantidad(): void
    {
        $this->assertSame('47.000', Formato::numero(47000));
        $this->assertSame('12', Formato::cantidad('12.00'));
        $this->assertSame('1,50', Formato::cantidad(1.5));
        $this->assertSame('-3', Formato::cantidad(-3));
    }

    public function test_fecha_dd_mm_aaaa(): void
    {
        $this->assertSame('03/09/2026', Formato::fecha(Carbon::create(2026, 9, 3)));
        $this->assertSame('01/02/2025', Formato::fecha('2025-02-01'));
        $this->assertSame('', Formato::fecha(null));
    }

    public function test_hora_hh_mm(): void
    {
        $this->assertSame('09:30', Formato::hora('09:30:00'));
        $this->assertSame('14:05', Formato::hora(Carbon::create(2026, 9, 3, 14, 5)));
        $this->assertSame('', Formato::hora(null));
        $this->assertSame('', Formato::hora(''));
    }
}
