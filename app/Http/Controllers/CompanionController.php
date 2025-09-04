<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Companion;
use Illuminate\Http\Request;
use App\Http\Requests\CompanionUpsertRequest;

class CompanionController extends Controller
{
    public function show($guestId)
    {
        try {
            $guest = Guest::with('companion')->findOrFail($guestId);
            return response()->json($guest->companion);
        } catch (\Throwable $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function upsert(Request $request, $guestId)
    {
        try {
            $guest = Guest::with('companion')->findOrFail($guestId);

            // Si enable_companion = false y ya NO existe companion, no permitir crear desde front
            // (si quieres permitir solo desde admin, maneja cabecera o auth).
            if (!$guest->enable_companion && !$guest->companion) {
                return response()->json(['message' => 'Companion is disabled for this guest'], 403);
            }

            $this->validate($request, CompanionUpsertRequest::rules());
            $data = $request->only(['name','lastname']);

            if ($guest->companion) {
                $old = $guest->companion->toArray();
                $guest->companion->update($data);

                $this->audit([
                    'guest_id'  => $guest->id,
                    'action'    => 'update',
                    'field'     => 'companion',
                    'old_value' => json_encode($old, JSON_UNESCAPED_UNICODE),
                    'new_value' => json_encode($guest->companion->toArray(), JSON_UNESCAPED_UNICODE),
                    'source'    => 'frontend',
                ]);

                return response()->json($guest->companion);
            }

            $companion = Companion::create(array_merge($data, ['guest_id' => $guest->id]));

            $this->audit([
                'guest_id'  => $guest->id,
                'action'    => 'create',
                'field'     => 'companion',
                'new_value' => json_encode($companion->toArray(), JSON_UNESCAPED_UNICODE),
                'source'    => 'frontend',
            ]);

            return response()->json($companion, 201);
        } catch (\Throwable $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function destroy($guestId)
    {
        $guest = Guest::with('companion')->findOrFail($guestId);
        if ($guest->companion) {
            $old = $guest->companion->toArray();
            $guest->companion->delete();

            $this->audit([
                'guest_id'  => $guest->id,
                'action'    => 'delete',
                'field'     => 'companion',
                'old_value' => json_encode($old, JSON_UNESCAPED_UNICODE),
                'source'    => 'admin',
            ]);
        }

        return response()->json(null, 204);
    }
}
