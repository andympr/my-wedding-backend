<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function statistics(Request $request)
    {
        // Get all guests with companions
        $guests = Guest::with('companion')->get();

        // Indicator 1: Total attendees breakdown
        $totalGuests = $guests->count();
        $totalCompanionsEnabled = $guests->where('enable_companion', true)->count();
        $totalCompanionsWithData = $guests->whereNotNull('companion')->count();
        $totalAttendees = $totalGuests + $totalCompanionsEnabled;

        // Indicator 2: Pending confirmations
        $guestsPending = $guests->where('confirm', 'pending');
        $totalGuestsPending = $guestsPending->count();
        $companionsPending = $guestsPending->where('enable_companion', true);
        $companionsWithDataPending = $companionsPending->whereNotNull('companion')->count();
        $companionsWithoutDataPending = $companionsPending->whereNull('companion')->count();
        $totalAttendeesPending = $totalGuestsPending + $companionsPending->count();

        // Indicator 3: Confirmed attendees
        $guestsConfirmed = $guests->where('confirm', 'yes');
        $totalGuestsConfirmed = $guestsConfirmed->count();
        $companionsConfirmed = $guestsConfirmed->whereNotNull('companion')->count();
        $totalAttendeesConfirmed = $totalGuestsConfirmed + $companionsConfirmed;

        // Indicator 4: Rejected attendees
        $guestsRejected = $guests->where('confirm', 'no');
        $totalGuestsRejected = $guestsRejected->count();
        $companionsRejected = $guestsRejected->where('enable_companion', true)->count();
        $totalAttendeesRejected = $totalGuestsRejected + $companionsRejected;

        // Indicator 5: Invitations status
        $totalInvitations = $totalGuests;
        $invitationsSent = $guests->where('invitation_sent', true)->count();
        $invitationsPending = $totalInvitations - $invitationsSent;

        // Indicator 6: Table assignments
        $guestsWithTable = $guests->filter(function ($guest) {
            return !is_null($guest->location) && $guest->location !== '';
        });
        $totalGuestsWithTable = $guestsWithTable->count();
        $companionsWithTable = $guestsWithTable->where('enable_companion', true)->count();
        $totalAttendeesWithTable = $totalGuestsWithTable + $companionsWithTable;

        $guestsWithoutTable = $guests->filter(function ($guest) {
            return is_null($guest->location) || $guest->location === '';
        });
        $totalGuestsWithoutTable = $guestsWithoutTable->count();
        $companionsWithoutTable = $guestsWithoutTable->where('enable_companion', true)->count();
        $totalAttendeesWithoutTable = $totalGuestsWithoutTable + $companionsWithoutTable;

        return response()->json([
            'indicator1' => [
                'name' => 'Total de Asistentes',
                'total_attendees' => $totalAttendees,
                'total_guests' => $totalGuests,
                'total_companions' => $totalCompanionsEnabled,
            ],
            'indicator2' => [
                'name' => 'Por Confirmar',
                'total_attendees_pending' => $totalAttendeesPending,
                'total_guests_pending' => $totalGuestsPending,
                'total_companions_with_data' => $companionsWithDataPending,
                'total_companions_without_data' => $companionsWithoutDataPending,
            ],
            'indicator3' => [
                'name' => 'Confirmados',
                'total_attendees_confirmed' => $totalAttendeesConfirmed,
                'total_guests_confirmed' => $totalGuestsConfirmed,
                'total_companions_confirmed' => $companionsConfirmed,
            ],
            'indicator4' => [
                'name' => 'Rechazados',
                'total_attendees_rejected' => $totalAttendeesRejected,
                'total_guests_rejected' => $totalGuestsRejected,
                'total_companions_rejected' => $companionsRejected,
            ],
            'indicator5' => [
                'name' => 'Invitaciones',
                'total_invitations' => $totalInvitations,
                'invitations_pending' => $invitationsPending,
                'invitations_sent' => $invitationsSent,
            ],
            'indicator6' => [
                'name' => 'Asignación de Mesas',
                'total_attendees_with_table' => $totalAttendeesWithTable,
                'total_attendees_without_table' => $totalAttendeesWithoutTable,
            ],
        ]);
    }
}
