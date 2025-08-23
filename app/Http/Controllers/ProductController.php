<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;


/**
 * @OA\Tag(
 *     name="Products",
 *     description="Operaciones con productos y materias primas"
 * )
 */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     tags={"Products"},
     *     summary="Listar todos los productos",
     *     @OA\Response(response=200, description="Lista de productos")
     * )
     */
    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     tags={"Products"},
     *     summary="Crear producto o materia prima",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type"},
     *             @OA\Property(property="type", type="string", enum={"product", "raw_material"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Producto creado")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     tags={"Products"},
     *     summary="Obtener producto por ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Producto encontrado")
     * )
     */
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     tags={"Products"},
     *     summary="Actualizar producto",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=200, description="Producto actualizado")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     tags={"Products"},
     *     summary="Eliminar producto",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Producto eliminado")
     * )
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    /**
     * @OA\Get(
     *     path="/api/raw-materials",
     *     tags={"Products"},
     *     summary="Listar todas las materias primas",
     *     @OA\Response(response=200, description="Lista de materias primas")
     * )
     */
    public function GetRawMaterials()
    {
        $materials = Product::where('type', 'raw_material')->get();
        return response()->json($materials);
    }

    /**
     * @OA\Get(
     *     path="/api/predefined-products",
     *     tags={"Products"},
     *     summary="Listar solo los productos predefinidos",
     *     @OA\Response(response=200, description="Lista de productos predefinidos")
     * )
     */
    public function predefinedProducts()
    {
        $products = Product::where('type', 'product')->get();
        return response()->json($products);
    }
    
}
