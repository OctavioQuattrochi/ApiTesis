<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Listar todos los productos
    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }

    // Crear un nuevo producto o materia prima
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:product,raw_material',
        ]);

        if ($data['type'] === 'product') {
            $validated = $request->validate([
                'name' => 'required|string',
                'color' => 'required|string',
                'quantity' => 'required|integer',
                'location' => 'required|string',
            ]);

            $product = Product::create([
                'type' => 'product',
                'name' => $validated['name'],
                'color' => $validated['color'],
                'quantity' => $validated['quantity'],
                'location' => $validated['location'],
            ]);
        } else {
            $validated = $request->validate([
                'material' => 'required|string',
                'supplier' => 'required|string',
                'cost' => 'required|numeric',
            ]);

            $product = Product::create([
                'type' => 'raw_material',
                'material' => $validated['material'],
                'supplier' => $validated['supplier'],
                'cost' => $validated['cost'],
                'final_price' => $validated['cost'] * 1.5,
            ]);
        }

        return response()->json(['message' => 'Producto creado exitosamente', 'product' => $product], 201);
    }

    // Mostrar un producto por ID
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    // Actualizar un producto
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if ($product->type === 'product') {
            $validated = $request->validate([
                'name' => 'sometimes|required|string',
                'color' => 'sometimes|required|string',
                'quantity' => 'sometimes|required|integer',
                'location' => 'sometimes|required|string',
            ]);

            $product->update($validated);
        } else {
            $validated = $request->validate([
                'material' => 'sometimes|required|string',
                'supplier' => 'sometimes|required|string',
                'cost' => 'sometimes|required|numeric',
            ]);

            if (isset($validated['cost'])) {
                $validated['final_price'] = $validated['cost'] * 1.5;
            }

            $product->update($validated);
        }

        return response()->json(['message' => 'Producto actualizado', 'product' => $product]);
    }

    // Eliminar un producto
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }
}
