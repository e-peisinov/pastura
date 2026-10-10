<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso y salida de la app de campo con el usuario del panel (P10, P12).
 * Cada ingreso crea un token con el nombre del celular; salir lo revoca.
 */
class IngresoController extends Controller
{
    public function ingresar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:150'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        if ($usuario === null || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => __('auth.inactivo')], 403);
        }

        $token = $usuario->createToken($datos['dispositivo'] ?? 'App de campo');

        return response()->json([
            'token' => $token->plainTextToken,
            'usuario' => $this->usuario($usuario),
        ]);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $this->usuario($request->user())]);
    }

    public function salir(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function usuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->rol->value,
            // P12: entra cualquiera activo; sin Editar en Recorridas, la app solo lleva al panel.
            'puede_recorrer' => $usuario->puedeEditar('recorridas'),
        ];
    }
}
