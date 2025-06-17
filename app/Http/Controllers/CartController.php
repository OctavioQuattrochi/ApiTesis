<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Obtener el carrito actual del usuario autenticado
     */
    public function index()
    {
        $user = Auth::guard('api')->user();
        $cart = $user->cart()->with('items.product', 'items.quote')->firstOrCreate();

        return response()->json($cart->load('items.product', 'items.quote'));
    }

    /**
     * Agregar un ítem al carrito
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
     * Eliminar un ítem del carrito
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
     * Vaciar todo el carrito
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
