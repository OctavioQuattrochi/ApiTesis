<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="UserDetails",
 *     description="Operaciones con detalles de usuario"
 * )
 */
class UserDetailController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/user-details",
     *     tags={"User Details"},
     *     summary="Crear o actualizar detalles del usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"address","city","province","dni","phone"},
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="province", type="string"),
     *             @OA\Property(property="dni", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="note", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Detalles guardados correctamente")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'dni' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'note' => 'nullable|string|max:500',
        ]);

        $user = Auth::guard('api')->user();

        $detail = UserDetail::updateOrCreate(
            ['user_id' => $user->id],
            [
                'address' => $request->address,
                'city' => $request->city,
                'province' => $request->province,
                'dni' => $request->dni,
                'phone' => $request->phone,
                'note' => $request->note,
            ]
        );

        Log::channel('usuarios')->info('Detalle de usuario actualizado', [
            'user_id' => $user->id,
            'detail_id' => $detail->id,
        ]);

        return response()->json([
            'message' => 'Detalles guardados correctamente',
            'details' => $detail
        ]);
    }
}
