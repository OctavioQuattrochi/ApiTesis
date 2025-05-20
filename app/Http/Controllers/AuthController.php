<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * @OA\Info(
 *     title="API de Autenticación",
 *     version="1.0.0"
 * )
 * @OA\Tag(
 *     name="Auth",
 *     description="Operaciones de autenticación"
 * )
 */
class AuthController extends Controller
{
    /**
     * Login y generación del token JWT.
     */
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Iniciar sesión",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token JWT generado"),
     *     @OA\Response(response=401, description="Credenciales inválidas")
     * )
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
    /**
     * @OA\Get(
     *     path="/api/me",
     *     tags={"Auth"},
     *     summary="Obtener usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Datos del usuario")
     * )
     */
    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * Cierra sesión invalidando el token.
     */
    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Cerrar sesión",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Sesión cerrada correctamente")
     * )
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

    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Auth"},
     *     summary="Registrar nuevo usuario",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","lastname","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="lastname", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario registrado y autenticado")
     * )
     */
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
