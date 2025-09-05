<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserAdminController extends Controller
{
    // Listar usuarios con filtros
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    // Ver detalle de usuario
    public function show($id)
    {
        $user = User::with('detail')->findOrFail($id);
        return response()->json($user);
    }

    // Cambiar rol de usuario
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:usuario,empleado,superadmin'
        ]);
        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        Log::channel('usuarios')->info('Rol actualizado', [
            'user_id' => $user->id,
            'new_role' => $user->role,
        ]);

        return response()->json(['message' => 'Rol actualizado', 'user' => $user]);
    }
}