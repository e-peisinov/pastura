<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Ir al panel desde la app sin volver a escribir la contraseña (P12). La app
 * pide con su token (`POST /api/v1/enlace-panel`) un enlace firmado que vence en un minuto; el pase que
 * lleva se guarda en la caché y se consume al usarlo, así sirve una sola vez.
 */
class PaseAlPanelController extends Controller
{
    private const VIGENCIA_SEGUNDOS = 60;

    public function crear(Request $request): JsonResponse
    {
        $pase = Str::random(64);
        Cache::put($this->clave($pase), $request->user()->id, self::VIGENCIA_SEGUNDOS);

        $url = URL::temporarySignedRoute('entrar-desde-app', now()->addSeconds(self::VIGENCIA_SEGUNDOS), ['pase' => $pase]);

        return response()->json(['url' => $url]);
    }

    public function entrar(Request $request, string $pase): RedirectResponse
    {
        // Con la firma buena el pase se consume, entre o no: sirve una sola vez.
        $usuarioId = $request->hasValidSignature() ? Cache::pull($this->clave($pase)) : null;

        if ($usuarioId === null) {
            return redirect()->route('login')->with('error', __('api.enlace_vencido'));
        }

        $usuario = User::find($usuarioId);

        if ($usuario === null || ! $usuario->activo) {
            return redirect()->route('login')->with('error', __('auth.inactivo'));
        }

        // Si en este navegador había otra sesión abierta, queda la del dueño del pase.
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function clave(string $pase): string
    {
        return 'pase-panel:'.hash('sha256', $pase);
    }
}
