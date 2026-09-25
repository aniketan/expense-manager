<?php

namespace App\Http\Controllers;

use App\Services\AiCategorization\AiCategorizationException;
use App\Services\AiCategorization\AiCategorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function categorize(Request $request, AiCategorizationService $service): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'type' => 'required|in:income,expense',
        ]);

        try {
            return response()->json($service->categorize($validated['description'], $validated['type']));
        } catch (AiCategorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI categorization failed: '.$e->getMessage()], 503);
        }
    }
}
