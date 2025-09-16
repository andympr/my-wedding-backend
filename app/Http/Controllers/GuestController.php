<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Http\Requests\GuestStoreRequest;
use App\Http\Requests\GuestUpdateRequest;
use App\Http\Requests\GuestUpdateByTokenRequest;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $q             = $request->input('q');
        $confirm       = $request->input('confirm'); // pending|yes|no
        $companion     = $request->input('companion'); // enabled|disabled
        $invitationSent = $request->input('invitation_sent'); // sent|not_sent
        $sort          = $request->input('sort', 'lastname');
        $order         = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage       = (int) $request->input('per_page', 10);

        $query = Guest::with('companion');

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'LIKE', "%$q%")
                   ->orWhere('lastname', 'LIKE', "%$q%")
                   ->orWhere('email', 'LIKE', "%$q%")
                   ->orWhere('phone', 'LIKE', "%$q%")
                   ->orWhereHas('companion', function ($companionQuery) use ($q) {
                       $companionQuery->where('name', 'LIKE', "%$q%")
                                    ->orWhere('lastname', 'LIKE', "%$q%");
                   });
            });
        }

        if (in_array($confirm, ['pending', 'yes', 'no'], true)) {
            $query->where('confirm', $confirm);
        }

        if ($companion === 'enabled') {
            $query->where('enable_companion', true);
        } elseif ($companion === 'disabled') {
            $query->where('enable_companion', false);
        }

        if ($invitationSent === 'sent') {
            $query->where('invitation_sent', true);
        } elseif ($invitationSent === 'not_sent') {
            $query->where('invitation_sent', false);
        }

        $sortable = ['name', 'lastname', 'email', 'confirm', 'created_at'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'lastname';
        }

        $query->orderBy($sort, $order);

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, GuestStoreRequest::rules());

            $payload = $request->only(['name','lastname','email','phone','enable_companion','notes','location','invitation_sent']);
            // Normalize empty strings to null for nullable fields
            foreach (['lastname','email','phone','notes','location'] as $k) {
                if (array_key_exists($k, $payload) && $payload[$k] === '') {
                    $payload[$k] = null;
                }
            }
            // Safe defaults
            $payload['enable_companion'] = (bool)($payload['enable_companion'] ?? false);
            $payload['invitation_sent'] = (bool)($payload['invitation_sent'] ?? false);
            $payload['confirm'] = 'pending';

            $guest = Guest::create($payload);

            // Handle companion on create
            $companion = $request->input('companion');
            if ($payload['enable_companion'] && is_array($companion)) {
                // Sanitize companion fields
                $cName = isset($companion['name']) && $companion['name'] !== '' ? $companion['name'] : null;
                $cLast = isset($companion['lastname']) && $companion['lastname'] !== '' ? $companion['lastname'] : null;
                $values = array_filter([
                    'name' => $cName,
                    'lastname' => $cLast,
                ], fn($v) => !is_null($v));
                if (!empty($values)) {
                    $created = $guest->companion()->create($values);
                    $this->audit([
                        'guest_id'  => $guest->id,
                        'action'    => 'create',
                        'field'     => 'companion',
                        'new_value' => json_encode($created->toArray(), JSON_UNESCAPED_UNICODE),
                        'source'    => 'admin',
                    ]);
                }
            }

            $this->audit([
                'guest_id' => $guest->id,
                'action'   => 'create',
                'field'    => null,
                'old_value'=> null,
                'new_value'=> $this->toAuditJson($guest->toArray()),
                'source'   => 'admin',
            ]);

            return response()->json($guest, 201);
        } catch (\Throwable $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        $guest = Guest::with('companion')->findOrFail($id);
        return response()->json($guest);
    }

    public function update(Request $request, $id)
    {
        try {
            $guest = Guest::findOrFail($id);
            $old   = $guest->toArray();

            $this->validate($request, GuestUpdateRequest::rules());

            $updateData = $request->only([
                'name','lastname','email','phone','enable_companion','confirm','notes','location','invitation_sent'
            ]);
            foreach (['lastname','email','phone','notes','location'] as $k) {
                if (array_key_exists($k, $updateData) && $updateData[$k] === '') {
                    $updateData[$k] = null;
                }
            }
            $guest->update($updateData);

            // Companion handling on update
            $enable = (bool) $guest->enable_companion;
            $companionPayload = $request->input('companion');
            if (!$enable && $guest->companion) {
                $oldC = $guest->companion->toArray();
                $guest->companion()->delete();
                $this->audit([
                    'guest_id'  => $guest->id,
                    'action'    => 'delete',
                    'field'     => 'companion',
                    'old_value' => $this->toAuditJson($oldC),
                    'source'    => 'admin',
                ]);
            } elseif ($enable && is_array($companionPayload)) {
                $cName = isset($companionPayload['name']) && $companionPayload['name'] !== '' ? $companionPayload['name'] : null;
                $cLast = isset($companionPayload['lastname']) && $companionPayload['lastname'] !== '' ? $companionPayload['lastname'] : null;
                $values = array_filter([
                    'name' => $cName,
                    'lastname' => $cLast,
                ], fn($v) => !is_null($v));
                if (!empty($values)) {
                    if ($guest->companion) {
                        $oldC = $guest->companion->toArray();
                        $guest->companion->update($values);
                        $this->audit([
                            'guest_id'  => $guest->id,
                            'action'    => 'update',
                            'field'     => 'companion',
                            'old_value' => $this->toAuditJson($oldC),
                            'new_value' => $this->toAuditJson($guest->companion->toArray()),
                            'source'    => 'admin',
                        ]);
                    } else {
                        $created = $guest->companion()->create($values);
                        $this->audit([
                            'guest_id'  => $guest->id,
                            'action'    => 'create',
                            'field'     => 'companion',
                            'new_value' => $this->toAuditJson($created->toArray()),
                            'source'    => 'admin',
                        ]);
                    }
                }
            }

            $this->audit([
                'guest_id' => $guest->id,
                'action'   => 'update',
                'field'    => null,
                'old_value'=> $this->toAuditJson($old),
                'new_value'=> $this->toAuditJson($guest->toArray()),
                'source'   => 'admin',
            ]);

            return response()->json($guest);
        } catch (\Throwable $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            $guest = Guest::findOrFail($id);
            $guest->delete();

            $this->audit([
                'guest_id' => $id,
                'action'   => 'delete',
                'source'   => 'admin',
            ]);

            return response()->json(null, 204);
        } catch (\Throwable $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function logs($id)
    {
        $guest = Guest::findOrFail($id);
        $logs = AuditLog::with('user')
            ->where('guest_id', $guest->id)
            ->orderByDesc('created_at')
            ->get();
        return response()->json($logs);
    }

    /**
     * Export filtered guests as Excel (.xlsx)
     */
    public function export(Request $request)
    {
        // Build the same base query and filters as index()
        $q             = $request->input('q');
        $confirm       = $request->input('confirm'); // pending|yes|no
        $companion     = $request->input('companion'); // enabled|disabled
        $invitationSent = $request->input('invitation_sent'); // sent|not_sent
        $sort          = $request->input('sort', 'lastname');
        $order         = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Guest::with('companion');

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'LIKE', "%$q%")
                   ->orWhere('lastname', 'LIKE', "%$q%")
                   ->orWhere('email', 'LIKE', "%$q%")
                   ->orWhere('phone', 'LIKE', "%$q%")
                   ->orWhereHas('companion', function ($companionQuery) use ($q) {
                       $companionQuery->where('name', 'LIKE', "%$q%")
                                    ->orWhere('lastname', 'LIKE', "%$q%");
                   });
            });
        }

        if (in_array($confirm, ['pending', 'yes', 'no'], true)) {
            $query->where('confirm', $confirm);
        }

        if ($companion === 'enabled') {
            $query->where('enable_companion', true);
        } elseif ($companion === 'disabled') {
            $query->where('enable_companion', false);
        }

        if ($invitationSent === 'sent') {
            $query->where('invitation_sent', true);
        } elseif ($invitationSent === 'not_sent') {
            $query->where('invitation_sent', false);
        }

        $sortable = ['name', 'lastname', 'email', 'confirm', 'created_at'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'lastname';
        }
        $query->orderBy($sort, $order);

        $filename = 'guests-' . date('Ymd-His') . '.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ];

        $callback = function () use ($query) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            // Header row
            $sheet->fromArray([
                ['Nombre', 'Apellido', 'Email', 'Teléfono', 'Permite Acompañante', 'Invitación Enviada', 'Confirmación', 'Acompañante Nombre', 'Acompañante Apellido', 'Mesa/Ubicación', 'Notas', 'Mensaje', 'Token', 'Creado', 'Actualizado', 'Confirmado', 'Rechazado']
            ], null, 'A1');

            $row = 2;
            // Load in chunks to avoid memory spikes
            $query->chunk(500, function ($rows) use (&$row, $sheet) {
                foreach ($rows as $g) {
                    $sheet->fromArray([
                        [
                            $g->name,
                            $g->lastname,
                            $g->email,
                            $g->phone,
                            $g->enable_companion ? 'Sí' : 'No',
                            $g->invitation_sent ? 'Sí' : 'No',
                            $g->confirm,
                            optional($g->companion)->name,
                            optional($g->companion)->lastname,
                            $g->location,
                            $g->notes,
                            $g->message,
                            $g->token,
                            optional($g->created_at)->toDateTimeString(),
                            optional($g->updated_at)->toDateTimeString(),
                            optional($g->confirmed_at)->toDateTimeString(),
                            optional($g->declined_at)->toDateTimeString(),
                        ]
                    ], null, 'A' . $row);
                    $row++;
                }
            });

            // Autosize columns A-Q
            foreach (range('A', 'Q') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    public function showByToken($token, Request $request)
    {
        $guest = $request->get('guest');
        return response()->json($guest->load('companion'));
    }

    public function updateByToken($token, Request $request)
    {
        $guest = $request->get('guest');

        $this->validate($request, GuestUpdateByTokenRequest::rules());

        $data = $request->only(['confirm', 'companion', 'email', 'phone', 'message', 'notes']);

        // Old values for audit
        $oldEmail   = $guest->email;
        $oldPhone   = $guest->phone;
        $oldMessage = $guest->message;
        $oldNotes   = $guest->notes;
        $oldCompanion = $guest->companion ? $guest->companion->toArray() : null;

        // Contact info
        if (array_key_exists('email', $data)) {
            $guest->email = $data['email'] === '' ? null : $data['email'];
        }
        if (array_key_exists('phone', $data)) {
            $guest->phone = $data['phone'] === '' ? null : $data['phone'];
        }

        // Normalize and assign optional text fields
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

        // Confirm and timestamps
        if (isset($data['confirm'])) {
            $old = $guest->confirm;
            $guest->confirm = $data['confirm'];
            if ($guest->confirm === 'yes') {
                $guest->confirmed_at = Carbon::now();
                $guest->declined_at = null;
            } elseif ($guest->confirm === 'no') {
                $guest->declined_at = Carbon::now();
                $guest->confirmed_at = null;
            }
        }

        $guest->save();

        // Companion: update/create only if there are real changes
        if (isset($data['companion']) && $guest->enable_companion) {
            $incoming = [
                'name' => isset($data['companion']['name']) && $data['companion']['name'] !== '' ? $data['companion']['name'] : null,
                'lastname' => isset($data['companion']['lastname']) && $data['companion']['lastname'] !== '' ? $data['companion']['lastname'] : null,
            ];
            $newValues = array_filter($incoming, fn($v) => !is_null($v));
            $currentValues = $guest->companion ? array_filter($guest->companion->only(['name','lastname']), fn($v) => !is_null($v)) : [];

            if ($guest->companion) {
                if (json_encode($newValues) !== json_encode($currentValues)) {
                    $guest->companion->update($newValues);
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
                if (!empty($newValues)) {
                    $created = $guest->companion()->create($newValues);
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

        // Audits for simple fields
        if (isset($data['confirm']) && $old !== $guest->confirm) {
            $this->audit([
                'guest_id'  => $guest->id,
                'action'    => $guest->confirm === 'yes' ? 'confirm' : 'decline',
                'field'     => 'confirm',
                'old_value' => $old ?? null,
                'new_value' => $guest->confirm,
                'source'    => 'frontend',
            ]);
        }
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

        return response()->json($guest->load('companion'));
    }
}
