<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Login y generación del token JWT.
     */
    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Retorna los datos del usuario autenticado.
     */
    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * Cierra sesión invalidando el token.
     */
    public function logout()
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * Devuelve el token con los datos necesarios.
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'lastname' => $request->lastname,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->respondWithToken($token);
    }
    
}
