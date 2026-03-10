<?php

namespace App\Http\Controllers;

use App\Models\BusinessContext;
use Illuminate\Http\Request;

class BusinessContextController extends Controller
{
    /**
     * Lista todos los contextos.
     * Ruta pública: n8n los consume para alimentar al bot.
     */
    public function index()
    {
        return response()->json(BusinessContext::orderBy('name')->get());
    }

    /**
     * Crea un nuevo contexto.
     * Solo admin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255|unique:business_context,name',
            'content' => 'required|string',
        ]);

        $ctx = BusinessContext::create([
            'name'       => $validated['name'],
            'content'    => $validated['content'],
            'updated_by' => $request->user()->email,
        ]);

        return response()->json($ctx, 201);
    }

    /**
     * Actualiza un contexto existente.
     * Solo admin.
     */
    public function update(Request $request, BusinessContext $businessContext)
    {
        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255|unique:business_context,name,' . $businessContext->id,
            'content' => 'sometimes|string',
        ]);

        $businessContext->update(array_merge($validated, [
            'updated_by' => $request->user()->email,
        ]));

        return response()->json($businessContext->fresh());
    }

    /**
     * Elimina un contexto.
     * Solo admin.
     */
    public function destroy(BusinessContext $businessContext)
    {
        $businessContext->delete();

        return response()->json(['status' => 'deleted']);
    }
}
