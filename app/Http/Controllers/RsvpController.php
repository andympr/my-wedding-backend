<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RsvpConfirmRequest;
use App\Http\Requests\RsvpUpdateEmailRequest;
use App\Http\Requests\RsvpUpdateByTokenRequest;
use Carbon\Carbon;

class RsvpController extends Controller
{
    // Detalles para el front por token
    public function details($token)
    {
        $guest = Guest::with('companion')->where('token', $token)->firstOrFail();
        $companionExists = (bool) $guest->companion;
        $canAddCompanion = $guest->enable_companion && !$companionExists;
        $companionEditable = $guest->enable_companion && $companionExists; // if false and exists, show read-only

        return response()->json([
            'guest' => [
                'id'               => $guest->id,
                'name'             => $guest->name,
                'lastname'         => $guest->lastname,
                'email'            => $guest->email,
                'phone'            => $guest->phone,
                'enable_companion' => $guest->enable_companion,
                'confirm'          => $guest->confirm,
                'notes'            => $guest->notes,
                'message'          => $guest->message,
                'can_add_companion' => $canAddCompanion,
                'companion_editable' => $companionEditable,
            ],
            'companion' => $guest->companion ? [
                'id'       => $guest->companion->id,
                'name'     => $guest->companion->name,
                'lastname' => $guest->companion->lastname,
            ] : null,
        ]);
    }

    // Confirmar asistencia: { confirm: "yes" | "no" }
    public function confirm(Request $request, $token)
    {
        $this->validate($request, RsvpConfirmRequest::rules());
        $status = $request->input('confirm');

        $guest = Guest::with('companion')->where('token', $token)->firstOrFail();
        $old   = $guest->confirm;

        $guest->confirm = $status;
        if ($status === 'yes') {
            $guest->confirmed_at = Carbon::now();
            $guest->declined_at = null;
        } else {
            $guest->declined_at = Carbon::now();
            $guest->confirmed_at = null;
        }
        $guest->save();

        // Se considera que confirmar invitado aplica al grupo (invitado + 1 acompañante si existe)
        $this->audit([
            'guest_id'  => $guest->id,
            'action'    => $status === 'yes' ? 'confirm' : 'decline',
            'field'     => 'confirm',
            'old_value' => $old,
            'new_value' => $status,
            'source'    => 'frontend',
        ]);

        return response()->json(['message' => 'RSVP saved', 'confirm' => $guest->confirm]);
    }

    // Actualizar email/teléfono del invitado
    public function updateEmail(Request $request, $token)
    {
        $this->validate($request, RsvpUpdateEmailRequest::rules());

        $guest = Guest::where('token', $token)->firstOrFail();

        $old = [
            'email' => $guest->email,
            'phone' => $guest->phone,
        ];

        if ($request->has('email')) $guest->email = $request->input('email');
        if ($request->has('phone')) $guest->phone = $request->input('phone');
        $guest->save();

        $this->audit([
            'guest_id'  => $guest->id,
            'action'    => 'update',
            'field'     => 'contact',
            'old_value' => json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_value' => json_encode(['email' => $guest->email, 'phone' => $guest->phone], JSON_UNESCAPED_UNICODE),
            'source'    => 'frontend',
        ]);

        return response()->json(['message' => 'Contact updated']);
    }

    public function showByToken($token, Request $request)
    {
        $guest = $request->get('guest');
        $guest->load('companion');
        $companionExists = (bool) $guest->companion;
        $canAddCompanion = $guest->enable_companion && !$companionExists;
        $companionEditable = $guest->enable_companion && $companionExists;
        return response()->json([
            'guest' => array_merge(
                $guest->only(['id', 'name', 'lastname', 'confirm', 'enable_companion']),
                [
                    'can_add_companion' => $canAddCompanion,
                    'companion_editable' => $companionEditable,
                ]
            ),
            'companion' => $guest->companion ? $guest->companion->only(['name', 'lastname']) : null,
        ]);
    }

    public function updateByToken($token, Request $request)
    {
        $guest = $request->get('guest');

        $this->validate($request, RsvpUpdateByTokenRequest::rules());
        $data = $request->only(['confirm', 'companion', 'message', 'notes']);

        if (isset($data['confirm'])) {
            $guest->confirm = $data['confirm'];
        }

        // Normalize empty strings to null for optional text fields
        if (array_key_exists('message', $data) && $data['message'] === '') {
            $data['message'] = null;
        }
        if (array_key_exists('notes', $data) && $data['notes'] === '') {
            $data['notes'] = null;
        }
        if (array_key_exists('message', $data)) {
            $guest->message = $data['message'];
        }
        if (array_key_exists('notes', $data)) {
            $guest->notes = $data['notes'];
        }

        $guest->save();

        if (isset($data['companion']) && $guest->enable_companion) {
            if ($guest->companion) {
                $guest->companion->update($data['companion']);
            } else {
                $guest->companion()->create($data['companion']);
            }
        }

        return response()->json([
            'message' => 'RSVP actualizado correctamente',
            'guest' => $guest->load('companion')
        ]);
    }
}
