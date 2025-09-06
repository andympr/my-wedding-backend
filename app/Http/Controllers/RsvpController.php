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
        $data = $request->only(['confirm', 'companion', 'message', 'notes', 'email', 'phone']);

        // Keep old values to audit changes
        $oldEmail   = $guest->email;
        $oldPhone   = $guest->phone;
        $oldMessage = $guest->message;
        $oldNotes   = $guest->notes;
        $oldCompanion = $guest->companion ? $guest->companion->toArray() : null;

        // Contact info (optional)
        if (array_key_exists('email', $data)) {
          $guest->email = $data['email'] === '' ? null : $data['email'];
        }
        if (array_key_exists('phone', $data)) {
          $guest->phone = $data['phone'] === '' ? null : $data['phone'];
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

        // Confirm status (optional) with timestamps and audit similar to confirm()
        if (isset($data['confirm'])) {
          $old = $guest->confirm;
          $guest->confirm = $data['confirm'];
          if ($guest->confirm === 'yes') {
            $guest->confirmed_at = Carbon::now();
            $guest->declined_at = null;
          } else if ($guest->confirm === 'no') {
            $guest->declined_at = Carbon::now();
            $guest->confirmed_at = null;
          }
          // Defer audit until after save (guest id present)
        }

        $guest->save();

        if (isset($data['companion']) && $guest->enable_companion) {
          // Sanitize incoming companion values (empty strings -> null)
          $incoming = [
            'name' => isset($data['companion']['name']) && $data['companion']['name'] !== '' ? $data['companion']['name'] : null,
            'lastname' => isset($data['companion']['lastname']) && $data['companion']['lastname'] !== '' ? $data['companion']['lastname'] : null,
          ];
          // Current raw values (can be null)
          $currName = $guest->companion ? $guest->companion->name : null;
          $currLast = $guest->companion ? $guest->companion->lastname : null;
          // Target keeps current when not provided in payload
          $target = [
            'name' => !is_null($incoming['name']) ? $incoming['name'] : $currName,
            'lastname' => !is_null($incoming['lastname']) ? $incoming['lastname'] : $currLast,
          ];
          // Comparable filtered arrays (remove nulls)
          $targetCmp = array_filter($target, fn($v) => !is_null($v));
          $currentCmp = array_filter(['name' => $currName, 'lastname' => $currLast], fn($v) => !is_null($v));

          if ($guest->companion) {
            // Update only if changed
            if (json_encode($targetCmp) !== json_encode($currentCmp)) {
              $guest->companion->update($target);
              $this->audit([
                'guest_id'  => $guest->id,
                'action'    => 'update',
                'field'     => 'companion',
                'old_value' => $oldCompanion ? $this->toAuditJson($oldCompanion) : null,
                'new_value' => $this->toAuditJson($guest->companion->toArray()),
                'source'    => 'frontend',
              ]);
            }
          } else {
            // Create only if target has any value
            if (!empty($targetCmp)) {
              $created = $guest->companion()->create($targetCmp);
              $this->audit([
                'guest_id'  => $guest->id,
                'action'    => 'create',
                'field'     => 'companion',
                'old_value' => null,
                'new_value' => $this->toAuditJson($created->toArray()),
                'source'    => 'frontend',
              ]);
            }
          }
        }

        // Audit after save if confirm was provided
        if (isset($data['confirm']) && ($old ?? null) !== $guest->confirm) {
          $this->audit([
            'guest_id'  => $guest->id,
            'action'    => $guest->confirm === 'yes' ? 'confirm' : 'decline',
            'field'     => 'confirm',
            'old_value' => $old ?? null,
            'new_value' => $guest->confirm,
            'source'    => 'frontend',
          ]);
        }

        // Audit simple text fields changes
        if ($oldEmail !== $guest->email) {
          $this->audit([
            'guest_id'  => $guest->id,
            'action'    => 'update',
            'field'     => 'email',
            'old_value' => $oldEmail ?? '',
            'new_value' => $guest->email ?? '',
            'source'    => 'frontend',
          ]);
        }
        if ($oldPhone !== $guest->phone) {
          $this->audit([
            'guest_id'  => $guest->id,
            'action'    => 'update',
            'field'     => 'phone',
            'old_value' => $oldPhone ?? '',
            'new_value' => $guest->phone ?? '',
            'source'    => 'frontend',
          ]);
        }
        if ($oldMessage !== $guest->message) {
          $this->audit([
            'guest_id'  => $guest->id,
            'action'    => 'update',
            'field'     => 'message',
            'old_value' => $oldMessage ?? '',
            'new_value' => $guest->message ?? '',
            'source'    => 'frontend',
          ]);
        }
        if ($oldNotes !== $guest->notes) {
          $this->audit([
            'guest_id'  => $guest->id,
            'action'    => 'update',
            'field'     => 'notes',
            'old_value' => $oldNotes ?? '',
            'new_value' => $guest->notes ?? '',
            'source'    => 'frontend',
          ]);
        }

        return response()->json([
          'message' => 'RSVP actualizado correctamente',
          'guest' => $guest->load('companion')
        ]);
    }
}
