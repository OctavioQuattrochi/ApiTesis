<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Models\Product;
use App\Models\Quote;
use App\Mail\PresupuestoConfirmacionMail;

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

        $neon = Product::where('material', 'Tira Neón')->first();
        $fuente = Product::where('material', 'Fuente')->first();
        $acrilico = Product::where('material', 'Acrílico')->first();
        $mano_obra = Product::where('material', 'Mano de obra')->first();

        $precio_neon = $neon->final_price ?? 6000;
        $precio_fuente = $fuente->final_price ?? 3750;
        $precio_acrilico = $acrilico->final_price ?? 0.3;
        $precio_mano_obra = $mano_obra->final_price ?? 10000;

        $imageName = $request->file('image')->getClientOriginalName();

        // Guardar la imagen en storage/app/public/quotes
        $imagePath = $request->file('image')->store('quotes', 'public');

        $prompt = <<<EOT
Eres un experto en fabricación de carteles de neón LED.
Tu tarea es calcular el presupuesto siguiendo exactamente estos pasos y fórmulas, sin improvisar ni inventar valores:

1. Calcula el contorno del cartel en centímetros:
   contorno = 2 * (alto + ancho)

2. Calcula los metros de tira de neón necesarios:
   metros_neon = contorno / 100

3. Calcula la cantidad de fuentes de alimentación necesarias:
   cantidad_fuentes = ceil(metros_neon / 4)

4. Calcula el área de acrílico necesario:
   area_acrilico = alto * ancho (en cm²)

5. Calcula el costo de cada componente:
   - Costo_neon = metros_neon * $precio_neon ARS
   - Costo_fuente = cantidad_fuentes * $precio_fuente ARS
   - Costo_acrilico = area_acrilico * $precio_acrilico ARS

6. Calcula el costo de mano de obra:
   - cantidad_mano_obra = metros_neon
   - Costo_mano_obra = cantidad_mano_obra * $precio_mano_obra ARS

7. Multiplica todos los costos por la cantidad de carteles.

8. Muestra el resultado en el siguiente formato, usando los títulos y el orden exacto:

Presupuesto para Cartel de Neón LED

- Metros de neón: [metros_neon] metros x $precio_neon ARS = [Costo_neon] ARS
- Fuentes: [cantidad_fuentes] x $precio_fuente ARS = [Costo_fuente] ARS
- Acrílico: [area_acrilico] cm² x $precio_acrilico ARS = [Costo_acrilico] ARS
- Mano de obra: [cantidad_mano_obra] x $precio_mano_obra ARS = [Costo_mano_obra] ARS
- Cantidad de carteles: {$request->quantity}
- TOTAL: $[valor numérico] ARS

No escribas el total en ningún otro lugar, solo en la última línea con el formato exacto.
No improvises ni inventes valores. Usa solo las fórmulas y datos proporcionados.

Datos del diseño:
- Imagen: $imageName
- Alto: {$request->height} cm
- Ancho: {$request->width} cm
- Color: {$request->color}
- Cantidad de carteles: {$request->quantity}
EOT;

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un asistente técnico de presupuestos para carteles de neón LED.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 1500,
                ]);

            if ($response->failed()) {
                Log::channel('presupuestos')->error('Error al obtener presupuesto de ChatGPT', ['response' => $response->body()]);
                return response()->json(['error' => 'Error al obtener presupuesto de ChatGPT'], 500);
            }

            $data = $response->json();
            $presupuesto = $data['choices'][0]['message']['content'] ?? 'No se pudo calcular el presupuesto.';

            Log::channel('presupuestos')->info('Texto presupuesto generado por OpenAI', ['presupuesto' => $presupuesto]);

            $estimated_price = null;
            if (preg_match_all('/TOTAL:\s*\$?([\d\.,]+)\s*ARS/i', $presupuesto, $matches)) {
                $num = end($matches[1]);
                $num = preg_replace('/[^\d\.,]/', '', $num);
                $num = str_replace(',', '.', $num);
                $estimated_price = is_numeric($num) ? floatval($num) : null;
            } elseif (preg_match_all('/TOTAL:\s*\$?([\d\.,]+)/i', $presupuesto, $matches)) {
                $num = end($matches[1]);
                $num = preg_replace('/[^\d\.,]/', '', $num);
                $num = str_replace(',', '.', $num);
                $estimated_price = is_numeric($num) ? floatval($num) : null;
            }

            Log::channel('presupuestos')->info('Valor extraído para estimated_price', ['estimated_price' => $estimated_price]);

            $quote = Quote::create([
                'user_id' => auth()->id(),
                'length_cm' => $request->width,
                'height_cm' => $request->height,
                'width_cm' => $request->width,
                'color' => $request->color,
                'image' => $imagePath,
                'quantity' => $request->quantity,
                'estimated_price' => $estimated_price,
                'raw_response' => json_encode($data),
                'breakdown' => $presupuesto,
                'status' => 'pendiente',
            ]);

            Log::channel('presupuestos')->info('Presupuesto creado', ['quote_id' => $quote->id, 'data' => $quote]);

            return response()->json([
                'estimated_price' => $estimated_price,
                'quote_id' => $quote->id,
                'breakdown' => $presupuesto,
                'image' => $imagePath,
                'raw' => $data,
            ]);
        } catch (\Exception $e) {
            Log::channel('presupuestos')->error('Excepción general', ['message' => $e->getMessage()]);
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

        $query = Quote::with('user')->latest();

        if ($user->role !== 'superadmin') {
            $query->where('user_id', $user->id);
        }

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
            'breakdown' => $quote->breakdown,
            'status' => $quote->status,
            'image' => $quote->image,
            'created_at' => $quote->created_at,
            'updated_at' => $quote->updated_at,
            'user' => $quote->user,
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
            Log::channel('presupuestos')->error('Presupuesto no encontrado', ['quote_id' => $id]);
            return response()->json(['error' => 'Presupuesto no encontrado'], 404);
        }

        $request->validate([
            'status' => 'nullable|string|in:' . implode(',', \App\Models\Quote::STATUSES),
            'estimated_price' => 'nullable|numeric',
            'breakdown' => 'nullable|string',
        ]);

        $oldStatus = $quote->status;
        $data = $request->only(['estimated_price', 'breakdown', 'status']);
        $quote->update($data);

        Log::channel('presupuestos')->info('Presupuesto actualizado', [
            'quote_id' => $quote->id,
            'old_status' => $oldStatus,
            'new_status' => $data['status'] ?? null,
            'estimated_price' => $data['estimated_price'] ?? null,
        ]);

        if (
            isset($data['status']) &&
            $data['status'] === 'esperando_confirmacion' &&
            $oldStatus !== 'esperando_confirmacion'
        ) {
            Mail::to($quote->user->email)->send(new PresupuestoConfirmacionMail($quote));
            Log::channel('presupuestos')->info('Email de confirmación enviado', [
                'quote_id' => $quote->id,
                'email' => $quote->user->email,
            ]);
        }

        return response()->json(['message' => 'Presupuesto actualizado', 'quote' => $quote]);
    }
}
