<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


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
        Log::channel('produccion')->info('Listado de productos consultado', ['total' => $products->count()]);
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
                'quantity' => 0,
                'final_price' => $validated['cost'] * 1.5,
            ]);
        }

        Log::channel('produccion')->info('Producto creado', [
            'product_id' => $product->id,
            'type' => $product->type,
            'name' => $product->name ?? null,
            'color' => $product->color ?? null,
        ]);

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
        Log::channel('produccion')->info('Detalle de producto consultado', ['product_id' => $product->id]);
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

        Log::channel('produccion')->info('Producto actualizado', [
            'product_id' => $product->id,
            'type' => $product->type,
            'name' => $product->name ?? null,
            'color' => $product->color ?? null,
        ]);

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

        Log::channel('produccion')->info('Producto eliminado', ['product_id' => $id]);

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
        Log::channel('produccion')->info('Listado de materias primas consultado', ['total' => $materials->count()]);
        return response()->json($materials);
    }

    /**
     * @OA\Put(
     *     path="/api/raw-materials/{id}/add-stock",
     *     tags={"Products"},
     *     summary="Agregar stock a una materia prima",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(property="quantity", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Stock actualizado")
     * )
     */
    public function addStockRawMaterial(Request $request, $id)
    {
        $product = Product::where('type', 'raw_material')->findOrFail($id);

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product->quantity += $request->quantity;
        $product->save();

        Log::channel('produccion')->info('Stock de materia prima incrementado', [
            'product_id' => $product->id,
            'added_quantity' => $request->quantity,
            'new_quantity' => $product->quantity,
        ]);

        return response()->json(['message' => 'Stock actualizado', 'product' => $product]);
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
        Log::channel('produccion')->info('Listado de productos predefinidos consultado', ['total' => $products->count()]);
        return response()->json($products);
    }

    /**
     * Consultar el stock actual de productos (base + variante/color) incluyendo el estado de producción
     */
    public function stock(Request $request)
    {
        $products = Product::select('id', 'name', 'color', 'quantity')
            ->where('type', 'product')
            ->orderBy('name')
            ->get();

        $result = $products->map(function ($product) {
            $lastBatch = ProductionBatch::where('product_id', $product->id)
                ->orderBy('updated_at', 'desc')
                ->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'color' => $product->color,
                'quantity' => $product->quantity,
                'status' => $lastBatch ? $lastBatch->status : null,
            ];
        });

        Log::channel('produccion')->info('Listado de stock consultado', ['total' => $result->count()]);

        return response()->json($result);
    }
}
