<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserRole
{
    public function handle(Request $request, Closure $next, string $requiredRole)
    {
        $user = Auth::guard('api')->user();

        if (!$user || !$user->role) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $roleHierarchy = [
            'usuario' => 1,
            'empleado' => 2,
            'superadmin' => 3,
        ];

        $userLevel = $roleHierarchy[$user->role] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 99;

        if ($userLevel < $requiredLevel) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
