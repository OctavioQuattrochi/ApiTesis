<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;


/**
 * @OA\Tag(
 *     name="Cart",
 *     description="Operaciones relacionadas al carrito de compras"
 * )
 */
class CartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/cart",
     *     tags={"Cart"},
     *     summary="Obtener el carrito actual del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Carrito actual con productos y presupuestos")
     * )
     */
    public function index()
    {
        $user = Auth::guard('api')->user();
        $cart = $user->cart()->with('items.product', 'items.quote')->firstOrCreate();

        return response()->json($cart->load('items.product', 'items.quote'));
    }

    /**
     * @OA\Post(
     *     path="/api/cart/items",
     *     tags={"Cart"},
     *     summary="Agregar un ítem al carrito",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity", "price_unit"},
     *             @OA\Property(property="product_id", type="integer", example=1),
     *             @OA\Property(property="quote_id", type="integer", example=2),
     *             @OA\Property(property="quantity", type="integer", example=3),
     *             @OA\Property(property="price_unit", type="number", format="float", example=123.45)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ítem agregado al carrito"),
     *     @OA\Response(response=422, description="Error de validación o datos incompletos")
     * )
     */
    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'quantity' => 'required|integer|min:1',
            'price_unit' => 'required|numeric|min:0',
        ]);

        if (!$request->product_id && !$request->quote_id) {
            return response()->json(['error' => 'Se debe enviar product_id o quote_id'], 422);
        }

        $user = Auth::guard('api')->user();
        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);

        $subtotal = $request->quantity * $request->price_unit;

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $request->product_id,
            'quote_id' => $request->quote_id,
            'quantity' => $request->quantity,
            'price_unit' => $request->price_unit,
            'subtotal' => $subtotal,
        ]);

        return response()->json(['message' => 'Ítem agregado al carrito', 'item' => $item], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/cart/items/{id}",
     *     tags={"Cart"},
     *     summary="Eliminar un ítem del carrito",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del ítem a eliminar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Ítem eliminado del carrito"),
     *     @OA\Response(response=403, description="No autorizado")
     * )
     */
    public function removeItem($id)
    {
        $user = Auth::guard('api')->user();
        $item = CartItem::findOrFail($id);

        if ($item->cart->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $item->delete();

        return response()->json(['message' => 'Ítem eliminado del carrito']);
    }

    /**
     * @OA\Delete(
     *     path="/api/cart",
     *     tags={"Cart"},
     *     summary="Vaciar todo el carrito",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Carrito vaciado correctamente")
     * )
     */
    public function clear()
    {
        $user = Auth::guard('api')->user();
        $cart = $user->cart;

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json(['message' => 'Carrito vaciado correctamente']);
    }
}
