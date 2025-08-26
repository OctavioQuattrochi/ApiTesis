<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Quote;

/**
 * @OA\Tag(
 *     name="Analyzer",
 *     description="Procesamiento de imagen para generar presupuesto"
 * )
 */
class AnalyzerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/analyze",
     *     tags={"Analyzer"},
     *     summary="Analizar una imagen y generar presupuesto estimado usando IA",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image", "height", "width", "color", "quantity"},
     *                 @OA\Property(property="image", type="string", format="binary"),
     *                 @OA\Property(property="height", type="number", format="float"),
     *                 @OA\Property(property="width", type="number", format="float"),
     *                 @OA\Property(property="color", type="string"),
     *                 @OA\Property(property="quantity", type="integer", minimum=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Presupuesto generado exitosamente"),
     *     @OA\Response(response=422, description="Datos inválidos"),
     *     @OA\Response(response=500, description="Error del servidor o de la IA")
     * )
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'image' => 'required|image',
            'height' => 'required|numeric',
            'width' => 'required|numeric',
            'color' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        // Obtén los precios de la base de datos
        $neon = Product::where('material', 'Tira Neón')->first();
        $fuente = Product::where('material', 'Fuente')->first();
        $acrilico = Product::where('material', 'Acrílico')->first();

        $precio_neon = $neon->final_price ?? 5000;
        $precio_fuente = $fuente->final_price ?? 3000;
        $precio_acrilico = $acrilico->final_price ?? 0.5;

        $imageName = $request->file('image')->getClientOriginalName();

        // Prompt detallado para ChatGPT (NO uses xxxx como ejemplo)
        $prompt = <<<EOT
Eres un experto en fabricación de carteles de neón LED. 
Te daré los datos de un diseño y tus tareas son:
- Estimar la cantidad de metros de tira de neón necesarios (vectorizando el contorno de la imagen).
- Calcular cuántas fuentes de alimentación se requieren (1 fuente cada 4 metros de neón).
- Calcular el área de acrílico necesario (alto x ancho en cm).
- Usar los siguientes precios: 
  - Tira Neón: $precio_neon ARS por metro
  - Fuente: $precio_fuente ARS por unidad
  - Acrílico: $precio_acrilico ARS por cm²
- El diseño tiene las siguientes características:
  - Imagen: $imageName (vectoriza el contorno para estimar metros de neón)
  - Alto: {$request->height} cm
  - Ancho: {$request->width} cm
  - Color: {$request->color}
  - Cantidad de carteles: {$request->quantity}
- Devuelve el presupuesto estimado en ARS, detallando:
  - Metros de neón necesarios y costo
  - Cantidad de fuentes y costo
  - Área de acrílico y costo
  - Total para la cantidad solicitada (indica el total con el formato: TOTAL: $[valor numérico])
  - Un breve desglose de cómo hiciste el cálculo
EOT;

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un asistente técnico de presupuestos para carteles de neón LED.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 500,
                ]);

            if ($response->failed()) {
                Log::error('Error al obtener presupuesto de ChatGPT', ['response' => $response->body()]);
                return response()->json(['error' => 'Error al obtener presupuesto de ChatGPT'], 500);
            }

            $data = $response->json();
            $presupuesto = $data['choices'][0]['message']['content'] ?? 'No se pudo calcular el presupuesto.';

            // Loguea el texto completo del presupuesto para debug
            Log::info('Texto presupuesto generado por OpenAI', ['presupuesto' => $presupuesto]);

            // Extrae el total del presupuesto del texto (busca "TOTAL: $[valor numérico]" o "Total: [valor] ARS")
            $estimated_price = null;
            if (preg_match('/TOTAL:\s*\$?([\d\.,]+)/i', $presupuesto, $matches)) {
                $num = preg_replace('/[^\d\.,]/', '', $matches[1]);
                $num = str_replace(',', '.', $num);
                $estimated_price = is_numeric($num) ? floatval($num) : null;
            } elseif (preg_match('/Total.*?([\d\.,]+)\s*ARS/i', $presupuesto, $matches)) {
                $num = preg_replace('/[^\d\.,]/', '', $matches[1]);
                $num = str_replace(',', '.', $num);
                $estimated_price = is_numeric($num) ? floatval($num) : null;
            }

            Log::info('Valor extraído para estimated_price', ['estimated_price' => $estimated_price]);

            // Guarda el presupuesto como quote
            $quote = Quote::create([
                'user_id' => auth()->id(),
                'length_cm' => null,
                'height_cm' => $request->height,
                'width_cm' => $request->width,
                'color' => $request->color,
                'quantity' => $request->quantity,
                'estimated_price' => $estimated_price,
                'raw_response' => json_encode($data),
                'status' => 'pendiente',
            ]);

            return response()->json([
                'estimated_price' => $estimated_price,
                'quote_id' => $quote->id,
                'raw' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Excepción general', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotes",
     *     tags={"Analyzer"},
     *     summary="Listar todos los presupuestos generados",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de presupuestos")
     * )
     */
    public function listQuotes(Request $request)
    {
        $user = $request->user();

        // Si es superadmin, ve todos los presupuestos
        if ($user->role === 'superadmin') {
            $query = Quote::with('user')->latest();
        } else {
            // Si no, solo los suyos
            $query = Quote::with('user')->where('user_id', $user->id)->latest();
        }

        // Filtro opcional por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->get()->values());
    }

    public function pendingQuotes()
    {
        return response()->json(
            Quote::with('user')
                ->where('status', 'pendiente')
                ->latest()
                ->get()
        );
    }

    /**
     * @OA\Get(
     *     path="/api/quotes/{id}",
     *     tags={"Analyzer"},
     *     summary="Obtener un presupuesto por ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Presupuesto encontrado"),
     *     @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show($id)
    {
        $quote = Quote::with('user')->find($id);
        if (!$quote) {
            return response()->json(['error' => 'Presupuesto no encontrado'], 404);
        }
        return response()->json([
            'id' => $quote->id,
            'user_id' => $quote->user_id,
            'height_cm' => $quote->height_cm,
            'width_cm' => $quote->width_cm,
            'color' => $quote->color,
            'quantity' => $quote->quantity,
            'estimated_price' => $quote->estimated_price,
            'status' => $quote->status,
            'created_at' => $quote->created_at,
            'updated_at' => $quote->updated_at,
            'user' => $quote->user,
            // agrega otros campos si los necesitas
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}",
     *     tags={"Analyzer"},
     *     summary="Actualizar un presupuesto por ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="estimated_price", type="number"),
     *             @OA\Property(property="breakdown", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Presupuesto actualizado"),
     *     @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function update(Request $request, $id)
    {
        $quote = Quote::find($id);
        if (!$quote) {
            return response()->json(['error' => 'Presupuesto no encontrado'], 404);
        }

        $request->validate([
            'status' => 'nullable|string|in:' . implode(',', \App\Models\Quote::STATUSES),
            'estimated_price' => 'nullable|numeric',
            'breakdown' => 'nullable|string',
        ]);

        $data = $request->only(['estimated_price', 'breakdown', 'status']);
        $quote->update($data);

        return response()->json(['message' => 'Presupuesto actualizado', 'quote' => $quote]);
    }
}
