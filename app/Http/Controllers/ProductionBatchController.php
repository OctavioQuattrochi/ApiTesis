<?php

namespace App\Http\Controllers;

use App\Models\ProductionBatch;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        ]);

        $data['created_by'] = Auth::id();

        $batch = ProductionBatch::create($data);

        // Si el lote se crea como "Finalizado", sumar al stock
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
        ]);

        $wasFinalized = $batch->status === 'Finalizado';

        $batch->fill($data);
        $batch->save();

        // Si pasa a "Finalizado" y antes no lo estaba, sumar al stock
        if ($batch->status === 'Finalizado' && !$wasFinalized) {
            $this->addToStock($batch);
        }

        return response()->json($batch);
    }

    // Sumar la cantidad del lote al stock del producto correspondiente
    protected function addToStock(ProductionBatch $batch)
    {
        $product = Product::where('id', $batch->product_id)
            ->when($batch->color, function ($query) use ($batch) {
                return $query->where('color', $batch->color);
            })
            ->first();

        if ($product) {
            $product->quantity += $batch->quantity;
            $product->save();
        }
    }
}
