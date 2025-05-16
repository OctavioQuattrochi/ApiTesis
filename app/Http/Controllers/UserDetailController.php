<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Auth;

class UserDetailController extends Controller
{
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

        // Crear o actualizar detalle del usuario
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

        return response()->json([
            'message' => 'Detalles guardados correctamente',
            'details' => $detail
        ]);
    }
}
