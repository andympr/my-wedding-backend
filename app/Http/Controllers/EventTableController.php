<?php

namespace App\Http\Controllers;

use App\Models\EventTable;
use App\Models\Guest;
use Illuminate\Http\Request;

class EventTableController extends Controller
{
    /**
     * Display a listing of tables with guest information.
     */
    public function index(Request $request)
    {
        $tables = EventTable::with(['guests.companion'])->get();
        
        return response()->json($tables->map(function($table) {
            return [
                'id' => $table->id,
                'name' => $table->name,
                'nro_asientos' => $table->nro_asientos,
                'position_x' => $table->position_x,
                'position_y' => $table->position_y,
                'guests' => $table->guests->map(function($guest) {
                    return [
                        'id' => $guest->id,
                        'name' => $guest->name,
                        'lastname' => $guest->lastname,
                        'enable_companion' => $guest->enable_companion,
                        'companion' => $guest->companion ? [
                            'name' => $guest->companion->name,
                            'lastname' => $guest->companion->lastname,
                        ] : null,
                    ];
                }),
                'assigned_count' => $table->assigned_count,
                'available_seats' => $table->available_seats,
                'is_full' => $table->is_full,
                'created_at' => $table->created_at,
                'updated_at' => $table->updated_at,
            ];
        }));
    }

    /**
     * Store a newly created table.
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255|unique:event_tables,name',
            'nro_asientos' => 'required|integer|min:1|max:20',
            'position_x' => 'numeric',
            'position_y' => 'numeric',
        ]);

        $table = EventTable::create([
            'name' => $request->name,
            'nro_asientos' => $request->nro_asientos,
            'position_x' => $request->position_x ?? 0,
            'position_y' => $request->position_y ?? 0,
        ]);

        return response()->json($table, 201);
    }

    /**
     * Display the specified table.
     */
    public function show($id)
    {
        $table = EventTable::with(['guests.companion'])->findOrFail($id);
        
        return response()->json([
            'id' => $table->id,
            'name' => $table->name,
            'nro_asientos' => $table->nro_asientos,
            'position_x' => $table->position_x,
            'position_y' => $table->position_y,
            'guests' => $table->guests->map(function($guest) {
                return [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'lastname' => $guest->lastname,
                    'enable_companion' => $guest->enable_companion,
                    'companion' => $guest->companion ? [
                        'name' => $guest->companion->name,
                        'lastname' => $guest->companion->lastname,
                    ] : null,
                ];
            }),
            'assigned_count' => $table->assigned_count,
            'available_seats' => $table->available_seats,
            'is_full' => $table->is_full,
            'created_at' => $table->created_at,
            'updated_at' => $table->updated_at,
        ]);
    }

    /**
     * Update the specified table.
     */
    public function update(Request $request, $id)
    {
        $table = EventTable::findOrFail($id);
        
        $this->validate($request, [
            'name' => 'required|string|max:255|unique:event_tables,name,' . $id,
            'nro_asientos' => 'required|integer|min:1|max:20',
            'position_x' => 'numeric',
            'position_y' => 'numeric',
        ]);

        // Check if reducing seats would cause overbooking
        if ($request->nro_asientos < $table->assigned_count) {
            return response()->json([
                'message' => 'No se puede reducir el número de asientos por debajo del número de invitados asignados (' . $table->assigned_count . ')'
            ], 400);
        }

        $table->update([
            'name' => $request->name,
            'nro_asientos' => $request->nro_asientos,
            'position_x' => $request->position_x ?? $table->position_x,
            'position_y' => $request->position_y ?? $table->position_y,
        ]);

        return response()->json($table);
    }

    /**
     * Remove the specified table.
     */
    public function destroy($id)
    {
        $table = EventTable::findOrFail($id);
        
        // Check if table has assigned guests
        if ($table->guests()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar una mesa que tiene invitados asignados'
            ], 400);
        }
        
        $table->delete();
        
        return response()->json(null, 204);
    }

    /**
     * Assign guests to a table
     */
    public function assignGuests(Request $request, $id)
    {
        $table = EventTable::findOrFail($id);
        
        $this->validate($request, [
            'guest_ids' => 'required|array',
            'guest_ids.*' => 'exists:guests,id',
        ]);

        $guests = Guest::whereIn('id', $request->guest_ids)->get();
        
        // Calculate total seats needed (guests + companions)
        $seatsNeeded = $guests->sum(function($guest) {
            return $guest->enable_companion ? 2 : 1;
        });
        
        // Check if table has enough available seats
        if ($table->available_seats < $seatsNeeded) {
            return response()->json([
                'message' => 'La mesa no tiene suficientes asientos disponibles. Necesarios: ' . $seatsNeeded . ', Disponibles: ' . $table->available_seats
            ], 400);
        }

        // Assign guests to table
        foreach ($guests as $guest) {
            $guest->update(['event_table_id' => $table->id]);
        }

        return response()->json([
            'message' => 'Invitados asignados correctamente',
            'table' => $table->load(['guests.companion']),
        ]);
    }

    /**
     * Remove guests from their current table
     */
    public function unassignGuests(Request $request)
    {
        $this->validate($request, [
            'guest_ids' => 'required|array',
            'guest_ids.*' => 'exists:guests,id',
        ]);

        Guest::whereIn('id', $request->guest_ids)->update(['event_table_id' => null]);

        return response()->json([
            'message' => 'Invitados desasignados correctamente'
        ]);
    }

    /**
     * Get unassigned guests
     */
    public function unassignedGuests()
    {
        $guests = Guest::with('companion')
            ->whereNull('event_table_id')
            ->orderBy('name')
            ->orderBy('lastname')
            ->get();

        return response()->json($guests->map(function($guest) {
            return [
                'id' => $guest->id,
                'name' => $guest->name,
                'lastname' => $guest->lastname,
                'enable_companion' => $guest->enable_companion,
                'companion' => $guest->companion ? [
                    'name' => $guest->companion->name,
                    'lastname' => $guest->companion->lastname,
                ] : null,
                'seats_needed' => $guest->enable_companion ? 2 : 1,
            ];
        }));
    }
}
