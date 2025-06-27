<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="Gestión de pedidos"
 * )
 */
class OrderController extends Controller
{
   /**
     * @OA\Post(
     *     path="/api/checkout",
     *     tags={"Orders"},
     *     summary="Confirmar el carrito y generar una orden",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Orden creada con éxito"),
     *     @OA\Response(response=400, description="El carrito está vacío"),
     *     @OA\Response(response=500, description="Error al procesar la orden")
     * )
     */
    public function checkout()
    {
        $user = Auth::guard('api')->user();
        $cart = $user->cart()->with('items.product')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['error' => 'El carrito está vacío'], 400);
        }

        DB::beginTransaction();

        try {
            $total = $cart->items->sum('subtotal');

            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'pending',
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quote_id' => $item->quote_id,
                    'quantity' => $item->quantity,
                    'price_unit' => $item->price_unit,
                    'subtotal' => $item->subtotal,
                ]);

                // Descontar stock si es producto físico
                if ($item->product && $item->product->type === 'product') {
                    $item->product->decrement('quantity', $item->quantity);
                }
            }

            // Vaciar el carrito
            $cart->items()->delete();

            DB::commit();

            return response()->json(['message' => 'Orden creada con éxito', 'order_id' => $order->id], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'No se pudo procesar la orden', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     tags={"Orders"},
     *     summary="Listar todos los pedidos del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filtrar por estado de la orden",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Listado de órdenes")
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::guard('api')->user();
        $query = $user->orders()->with('items.product', 'items.quote')->latest();

        if ($request->has('status')) {
            $status = $request->status;

            if (!in_array($status, Order::VALID_STATUSES)) {
                return response()->json(['error' => 'Estado inválido'], 422);
            }

            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{id}",
     *     tags={"Orders"},
     *     summary="Ver el detalle de un pedido específico",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del pedido",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Detalle del pedido"),
     *     @OA\Response(response=403, description="No autorizado")
     * )
     */
    public function show($id)
    {
        $user = Auth::guard('api')->user();
        $order = Order::with('items.product', 'items.quote')->findOrFail($id);

        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json($order);
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{id}/status",
     *     tags={"Orders"},
     *     summary="Cambiar el estado de una orden",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la orden",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="pending")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Estado actualizado"),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=422, description="Estado inválido")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', Order::VALID_STATUSES)
        ]);

        $order = Order::findOrFail($id);

        // Solo el dueño puede cambiar el estado (o podrías agregar un rol admin en el futuro)
        $user = Auth::guard('api')->user();
        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $order->status = $request->status;
        $order->save();

        return response()->json(['message' => 'Estado actualizado', 'order' => $order]);
    }

}
