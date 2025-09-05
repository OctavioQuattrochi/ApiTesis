<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="Gestión de pedidos"
 * )
 */
class OrderController extends Controller
{
    // Estados recomendados para órdenes
    const VALID_STATUSES = [
        'pending',      // Pedido creado, esperando pago
        'paid',         // Pagado y confirmado por admin
        'processing',   // En preparación
        'shipped',      // Enviado al cliente
        'delivered',    // Entregado al cliente
        'cancelled',    // Cancelado
    ];

    /**
     * @OA\Post(
     *     path="/api/checkout",
     *     tags={"Orders"},
     *     summary="Confirmar el carrito y generar una orden",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method", "items"},
     *             @OA\Property(property="payment_method", type="string", example="transferencia"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Orden creada con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="order_number", type="integer", example=123456),
     *             @OA\Property(property="total", type="number", format="float", example=13000)
     *         )
     *     ),
     *     @OA\Response(response=400, description="El carrito está vacío"),
     *     @OA\Response(response=422, description="Datos inválidos"),
     *     @OA\Response(response=500, description="Error al procesar la orden")
     * )
     */
    public function checkout(Request $request)
    {
        $user = Auth::guard('api')->user();

        $request->validate([
            'payment_method' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $total = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                $price_unit = $product->final_price ?? 0;
                $subtotal = $item['quantity'] * $price_unit;
                $total += $subtotal;

                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_unit' => $price_unit,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
            ]);

            foreach ($orderItems as $orderItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $orderItem['product_id'],
                    'quantity' => $orderItem['quantity'],
                    'price_unit' => $orderItem['price_unit'],
                    'subtotal' => $orderItem['subtotal'],
                ]);
                    // Reducir stock del producto
                $product = \App\Models\Product::find($orderItem['product_id']);
                if ($product && $product->type === 'product') {
                    $product->decrement('quantity', $orderItem['quantity']);
                }
            }

            Log::channel('usuarios')->info('Orden creada', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'total' => $order->total,
                'payment_method' => $order->payment_method,
            ]);

            DB::commit();

            return response()->json([
                'order_number' => $order->id,
                'total' => $order->total,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('usuarios')->error('Error al procesar la orden', [
                'user_id' => $user->id,
                'details' => $e->getMessage(),
            ]);
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

        if ($user->role === 'superadmin' || $user->role === 'empleado') {
            $query = Order::with('items.product', 'items.quote', 'user')->latest();
        } else {
            $query = $user->orders()->with('items.product', 'items.quote', 'user')->latest();
        }

        if ($request->has('status')) {
            $status = $request->status;

            if (!in_array($status, self::VALID_STATUSES)) {
                Log::channel('usuarios')->warning('Intento de filtrar órdenes por estado inválido', [
                    'user_id' => $user->id,
                    'status' => $status,
                ]);
                return response()->json(['error' => 'Estado inválido'], 422);
            }

            $query->where('status', $status);
        }

        Log::channel('usuarios')->info('Listado de órdenes consultado', [
            'user_id' => $user->id,
            'role' => $user->role,
            'status' => $request->status ?? 'all',
        ]);

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
        $order = Order::with('items.product', 'items.quote', 'user')->findOrFail($id);

        if (($user->role !== 'superadmin' && $user->role !== 'empleado') && $order->user_id !== $user->id) {
            Log::channel('usuarios')->warning('Intento de acceder a orden no autorizada', [
                'user_id' => $user->id,
                'order_id' => $id,
            ]);
            return response()->json(['error' => 'No autorizado'], 403);
        }

        Log::channel('usuarios')->info('Detalle de orden consultado', [
            'user_id' => $user->id,
            'order_id' => $id,
        ]);

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
            'status' => 'required|in:' . implode(',', self::VALID_STATUSES)
        ]);

        $order = Order::findOrFail($id);
        $user = Auth::guard('api')->user();

        if ($user->role !== 'superadmin' && $user->role !== 'empleado') {
            if ($order->user_id !== $user->id) {
                Log::channel('usuarios')->warning('Intento de cambiar estado de orden no autorizada', [
                    'user_id' => $user->id,
                    'order_id' => $id,
                ]);
                return response()->json(['error' => 'No autorizado'], 403);
            }
        }

        $oldStatus = $order->status;
        $order->status = $request->status;
        $order->save();

        Log::channel('usuarios')->info('Estado de orden actualizado', [
            'user_id' => $user->id,
            'order_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $order->status,
        ]);

        return response()->json(['message' => 'Estado actualizado', 'order' => $order]);
    }
}
