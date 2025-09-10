<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
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
            Log::channel('auth')->warning('Intento de login fallido', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $user = Auth::guard('api')->user();
        Log::channel('auth')->info('Login exitoso', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);
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
        $user = Auth::guard('api')->user();
        Log::channel('auth')->info('Consulta de usuario autenticado', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        return response()->json($user);
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
        $user = Auth::guard('api')->user();
        Log::channel('auth')->info('Logout', [
            'user_id' => $user ? $user->id : null,
            'email' => $user ? $user->email : null,
        ]);
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

        Log::channel('auth')->info('Usuario registrado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->respondWithToken($token);
    }

    /**
     * Actualiza los datos del perfil y detalles de envío del usuario autenticado.
     */
    /**
     * @OA\Put(
     *     path="/api/profile",
     *     tags={"Auth"},
     *     summary="Actualizar perfil",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="direccion", type="string"),
     *             @OA\Property(property="localidad", type="string"),
     *             @OA\Property(property="provincia", type="string"),
     *             @OA\Property(property="telefono", type="string"),
     *             @OA\Property(property="dni", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Perfil actualizado correctamente"),
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::guard('api')->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'direccion' => 'nullable|string|max:255',
            'localidad' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'dni' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $user->detail()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'address' => $data['direccion'] ?? '',
                'city' => $data['localidad'] ?? '',
                'province' => $data['provincia'] ?? '',
                'phone' => $data['telefono'] ?? '',
                'dni' => $data['dni'] ?? '',
            ]
        );

        Log::channel('auth')->info('Perfil actualizado', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json(['message' => 'Perfil actualizado correctamente']);
    }

    /**
     * Envía el enlace de recuperación de contraseña al email indicado.
     */
    /**
     * @OA\Post(
     *     path="/api/password/email",
     *     tags={"Auth"},
     *     summary="Enviar enlace de recuperación de contraseña",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Enlace de recuperación enviado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        Log::channel('auth')->info('Solicitud de recuperación de contraseña', [
            'email' => $request->email,
            'status' => $status,
            'ip' => $request->ip(),
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Enlace de recuperación enviado']);
        }

        return response()->json(['error' => 'No se pudo enviar el enlace'], 500);
    }

    /**
     * Restablece la contraseña del usuario.
     */
    /**
     * @OA\Post(
     *     path="/api/password/reset",
     *     tags={"Auth"},
     *     summary="Restablecer contraseña",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","password_confirmation","token"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contraseña restablecida con éxito"),
     *     @OA\Response(response=400, description="Solicitud incorrecta")
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
            'token' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::channel('auth')->error('Usuario no encontrado para restablecimiento', [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        Log::channel('auth')->info('Contraseña restablecida', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Contraseña restablecida con éxito']);
    }
}
