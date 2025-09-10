<?php

namespace App\Http\Controllers;

use App\Models\ProductionBatch;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Producción",
 *     description="Gestión de lotes de producción"
 * )
 */
class ProductionBatchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/produccion",
     *     tags={"Producción"},
     *     summary="Listar lotes de producción filtrados por estado",
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrar por estado (ej: Pendiente,En produccion)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de lotes de producción"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $query = ProductionBatch::with('product', 'creator');

        if ($status) {
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/produccion",
     *     tags={"Producción"},
     *     summary="Crear un nuevo lote de producción",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "quantity"},
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="color", type="string"),
     *             @OA\Property(property="quantity", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"Pendiente", "En produccion", "Finalizado"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lote de producción creado"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'color' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:1',
            'status' => 'in:Pendiente,En produccion,Finalizado',
            'price' => 'nullable|numeric|min:0',
        ]);

        $data['created_by'] = Auth::id();

        $batch = ProductionBatch::create($data);

        Log::channel('produccion')->info('Lote de producción creado', [
            'batch_id' => $batch->id,
            'product_id' => $batch->product_id,
            'color' => $batch->color,
            'quantity' => $batch->quantity,
            'status' => $batch->status,
            'created_by' => $batch->created_by,
            'price' => $batch->price ?? null,
        ]);

        if ($batch->status === 'Finalizado') {
            $this->addToStock($batch);
        }

        return response()->json($batch, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/produccion/{id}",
     *     tags={"Producción"},
     *     summary="Actualizar estado y/o cantidad de un lote de producción",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"Pendiente", "En produccion", "Finalizado"}),
     *             @OA\Property(property="quantity", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lote de producción actualizado"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $batch = ProductionBatch::findOrFail($id);

        $data = $request->validate([
            'status' => 'in:Pendiente,En produccion,Finalizado',
            'quantity' => 'integer|min:1',
            'price' => 'nullable|numeric|min:0',
        ]);

        $wasFinalized = $batch->status === 'Finalizado';

        $batch->fill($data);
        $batch->save();

        Log::channel('produccion')->info('Lote de producción actualizado', [
            'batch_id' => $batch->id,
            'new_status' => $batch->status,
            'quantity' => $batch->quantity,
            'price' => $batch->price ?? null,
        ]);
        
        if ($batch->status === 'Finalizado' && !$wasFinalized) {
            $this->addToStock($batch);
        }

        return response()->json($batch);
    }

    /**
     * Sumar la cantidad del lote al stock de la variante correspondiente
     */
    protected function addToStock(ProductionBatch $batch)
    {
        $product = Product::find($batch->product_id);
        $price = $product ? $product->final_price : null;

        $variant = ProductVariant::where('product_id', $batch->product_id)
            ->where('color', $batch->color)
            ->first();

        if ($variant) {
            $variant->quantity += $batch->quantity;
            if ($price !== null) {
                $variant->price = $price;
            }
            $variant->save();
        } else {
            ProductVariant::create([
                'product_id' => $batch->product_id,
                'color' => $batch->color,
                'quantity' => $batch->quantity,
                'price' => $price,
            ]);
        }
    }
}
