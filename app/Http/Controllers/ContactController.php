<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Permite que admin o asesor actualicen el nombre de un contacto.
     */
    public function updateName(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $contact->update([
            'name' => $validated['name'] ?? null,
        ]);

        return response()->json([
            'status' => 'updated',
            'contact' => [
                'id' => $contact->id,
                'phone' => $contact->phone,
                'name' => $contact->name,
            ],
        ]);
    }
}
