<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Http\Requests\GuestStoreRequest;
use App\Http\Requests\GuestUpdateRequest;
use App\Http\Requests\GuestUpdateByTokenRequest;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $q          = $request->input('q');
        $confirm    = $request->input('confirm'); // pending|yes|no
        $companion  = $request->input('companion'); // enabled|disabled
        $sort       = $request->input('sort', 'lastname');
        $order      = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage    = (int) $request->input('per_page', 10);

        $query = Guest::with('companion');

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'LIKE', "%$q%")
                   ->orWhere('lastname', 'LIKE', "%$q%")
                   ->orWhere('email', 'LIKE', "%$q%")
                   ->orWhere('phone', 'LIKE', "%$q%");
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

            $payload = $request->only(['name','lastname','email','phone','enable_companion','notes']);
            // Normalize empty strings to null for nullable fields
            foreach (['lastname','email','phone','notes'] as $k) {
                if (array_key_exists($k, $payload) && $payload[$k] === '') {
                    $payload[$k] = null;
                }
            }
            // Safe defaults
            $payload['enable_companion'] = (bool)($payload['enable_companion'] ?? false);
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
                'new_value'=> json_encode($guest->toArray(), JSON_UNESCAPED_UNICODE),
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
                'name','lastname','email','phone','enable_companion','confirm','notes'
            ]);
            foreach (['lastname','email','phone','notes'] as $k) {
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
                    'old_value' => json_encode($oldC, JSON_UNESCAPED_UNICODE),
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
                            'old_value' => json_encode($oldC, JSON_UNESCAPED_UNICODE),
                            'new_value' => json_encode($guest->companion->toArray(), JSON_UNESCAPED_UNICODE),
                            'source'    => 'admin',
                        ]);
                    } else {
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
            }

            $this->audit([
                'guest_id' => $guest->id,
                'action'   => 'update',
                'field'    => null,
                'old_value'=> json_encode($old, JSON_UNESCAPED_UNICODE),
                'new_value'=> json_encode($guest->toArray(), JSON_UNESCAPED_UNICODE),
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

    public function showByToken($token, Request $request)
    {
        $guest = $request->get('guest');
        return response()->json($guest->load('companion'));
    }

    public function updateByToken($token, Request $request)
    {
        $guest = $request->get('guest');

        $this->validate($request, GuestUpdateByTokenRequest::rules());

        $data = $request->only(['confirm', 'companion']);

        if (isset($data['confirm'])) {
            $guest->confirm = $data['confirm'];
        }

        $guest->save();

        if (isset($data['companion']) && $guest->enable_companion) {
            if ($guest->companion) {
                $guest->companion->update($data['companion']);
            } else {
                $guest->companion()->create($data['companion']);
            }
        }

        return response()->json($guest->load('companion'));
    }
}
