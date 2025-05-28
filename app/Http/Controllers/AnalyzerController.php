<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Quote;

class AnalyzerController extends Controller
{
    public function analyze(Request $request)
    {
        $request->validate([
            'image' => 'required|image',
            'height' => 'required|numeric',
            'width' => 'required|numeric',
            'color' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        $client = new Client();

        try {
            $multipart = [
                [
                    'name'     => 'image',
                    'contents' => fopen($request->file('image')->getRealPath(), 'r'),
                    'filename' => $request->file('image')->getClientOriginalName(),
                ],
                [
                    'name'     => 'height_cm',
                    'contents' => (string) $request->height,
                ],
                [
                    'name'     => 'width_cm',
                    'contents' => (string) $request->width,
                ],
            ];

            Log::debug('Payload enviado al microservicio', [
                'height_cm' => $request->height,
                'width_cm' => $request->width,
                'filename' => $request->file('image')->getClientOriginalName(),
            ]);

            $res = $client->request('POST', 'http://apianalyzer:5000/analyze', [
                'multipart' => $multipart,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $body = $res->getBody()->getContents();
            Log::debug('Respuesta microservicio', ['body' => $body]);

            $data = json_decode($body, true);
            if (!$data || !isset($data['length_cm'])) {
                throw new \Exception("Respuesta inválida del microservicio: $body");
            }

            $length_cm = $data['length_cm'];
            $metros_neon = $length_cm / 100;
            $rollos_neon = ceil($metros_neon / 5);
            $fuentes = ceil($metros_neon / 4);
            $area_cm2 = $request->height * $request->width;

            $default_prices = [
                'Tira Neón' => 5000,
                'Fuente' => 3000,
                'Acrílico' => 0.5,
            ];

            $neon = Product::where('material', 'Tira Neón')->first();
            $fuente = Product::where('material', 'Fuente')->first();
            $acrilico = Product::where('material', 'Acrílico')->first();

            $precio_neon = $neon->final_price ?? $default_prices['Tira Neón'];
            $precio_fuente = $fuente->final_price ?? $default_prices['Fuente'];
            $precio_acrilico = $acrilico->final_price ?? $default_prices['Acrílico'] * $area_cm2;

            $subtotal = ($rollos_neon * $precio_neon) + ($fuentes * $precio_fuente) + $precio_acrilico;
            $total = $subtotal * $request->quantity;

            $quote = Quote::create([
                'length_cm' => $length_cm,
                'height_cm' => $request->height,
                'width_cm' => $request->width,
                'color' => $request->color,
                'quantity' => $request->quantity,
                'estimated_price' => round($total, 2),
                'raw_response' => json_encode($data),
            ]);

            return response()->json([
                'estimated_price' => round($total, 2),
                'quantity' => $request->quantity,
                'breakdown' => [
                    'rollos_neon' => $rollos_neon,
                    'precio_por_rollo' => $precio_neon,
                    'fuentes' => $fuentes,
                    'precio_por_fuente' => $precio_fuente,
                    'precio_acrilico' => round($precio_acrilico, 2),
                    'medidas_cm' => [
                        'alto' => $request->height,
                        'ancho' => $request->width
                    ]
                ],
                'raw' => $data,
                'quote_id' => $quote->id
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = (string) $response->getBody();

            Log::error('Microservicio error', [
                'status' => $response->getStatusCode(),
                'body_raw' => $body,
                'headers' => $response->getHeaders(),
            ]);

            return response()->json([
                'error' => 'Error al analizar la imagen',
                'microservice_response' => $body ?: 'Respuesta vacía del microservicio',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Excepción general', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function listQuotes()
    {
        return response()->json(Quote::latest()->get());
    }
}
