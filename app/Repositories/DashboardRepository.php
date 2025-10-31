<?php

namespace App\Repositories;

class DashboardRepository
{
    /**
     * Return statistics about users (e.g. total, active, by role).
     */
    public function getUserStats()
    {
        // Query users table
        return [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', 1)->count(),
            'usersWaitingActivation' => User::where('is_active', 0)->count(),
            'totalCampers' => User::where('role_id', 1)->count(),
            'totalGroups' => User::where('role_id', 2)->count(),
            'totalCenter' => User::where('role_id', 3)->count(),
            'totalFournisseur' => User::where('role_id', 4)->count(),
            'totalGuides' => User::where('role_id', 5)->count(),
            'maleUser' => User::where('sexe', 'Male')->count(),
            'femaleUser' => User::where('sexe', 'Female')->count(),
            'usersByCity' => User::selectRaw('city, COUNT(*) as total')
                                ->groupBy('city')
                                ->orderByDesc('total')
                                ->get(),

            'newUsersThisMonth' => User::where('created_at', '>=', now()->subMonth())->count(),
        ];
    }

    /**
     * Return statistics about events and centers.
     */
    public function getEventStats()
    {

        return [
            'totalEvents' => Events::count(),
            'eventsThisMonth' => Events::where('created_at', '>=', now()->subMonth())->count(),
            'totalPendingEvents' => Events::where('status', 'pending')->count(),
            'totalScheduledEvents' => Events::where('status', 'scheduled')->count(),
            'totalFinishedEvents' => Events::where('status', 'finished')->count(),
            'totalCanceledEvents' => Events::where('status', 'canceled')->count(),
            'totalPostponedEvents' => Events::where('status', 'postponed')->count(),
            'totalFullEvents' => Events::where('status', 'full')->count(),

            //Scheduled events for the next month
            'scheduledNextMonth' => Events::where('status', 'scheduled')
                                        ->whereBetween('date_sortie', [now(), now()->addMonth()])
                                        ->get(),

            //events grouped by city
            'eventsByCity' => Events::selectRaw('ville_passente, COUNT(*) as total')
                                    ->groupBy('ville_passente')
                                    ->orderByDesc('total')
                                    ->get(),

            //average number of places remaining
            'avgPlacesRemaining' => Events::avg('nbr_place_restante'),

            //upcoming events count per category
            'upcomingByCategory' => Events::selectRaw('category, COUNT(*) as total')
                                        ->where('date_sortie', '>=', now())
                                        ->groupBy('category')
                                        ->get(),
        ];
    }

    /**
     * Return the most active profiles (top campers, guides, etc.).
     */
    public function getProfileStats()
    {
        // Query reservations/activities
        // Example: rank users by activity, get top 5 campers, top guides
        return 
        [
        'topRatedCenters' => Feedbacks::join('users', 'feedbacks.target_id', '=', 'users.id')
            ->where('users.role_id', 3) 
            ->select(
                'users.id as center_id',
                'users.name as center_name',
                'users.ville',
                DB::raw('AVG(feedbacks.note) as avg_rating'),
                DB::raw('COUNT(feedbacks.id) as total_feedbacks')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderByDesc('avg_rating')
            ->take(5) 
            ->get(),

        'topCentersWithHighestActivity' => Reservations_centre::join('users', 'reservations_centre.centre_id', '=', 'users.id')
            ->select(
                'users.id as center_id',
                'users.name as center_name',
                'users.ville',
                DB::raw('COUNT(*) as number_of_reservations')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderByDesc('number_of_reservations')
            ->take(5)
            ->get(),

        'lowestRatedCenters' => Feedbacks::join('users', 'feedbacks.target_id', '=', 'users.id')
            ->where('users.role_id', 3) 
            ->select(
                'users.id as center_id',
                'users.name as center_name',
                'users.ville',
                DB::raw('AVG(feedbacks.note) as avg_rating'),
                DB::raw('COUNT(feedbacks.id) as total_feedbacks')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderBy('avg_rating', 'asc')
            ->take(5) 
            ->get(),

        'centersWithLowestActivity' => Reservations_centre::join('users', 'reservations_centre.centre_id', '=', 'users.id')
            ->select(
                'users.id as center_id',
                'users.name as center_name',
                'users.ville',
                DB::raw('COUNT(*) as number_of_reservations')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderBy('number_of_reservations', 'asc')
            ->take(5)
            ->get(),


        // fournisseur stats
        'topRatedFournisseur' => Feedbacks::join('users', 'feedbacks.target_id', '=', 'users.id')
            ->where('users.role_id', 4) 
            ->select(
                'users.id as fournisseur_id',
                'users.name as fournisseur_name',
                'users.ville',
                DB::raw('AVG(feedbacks.note) as avg_rating'),
                DB::raw('COUNT(feedbacks.id) as total_feedbacks')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderByDesc('avg_rating')
            ->take(5) 
            ->get(),

        'topFournisseurWithHighestActivity' => Reservations_materielles::join('users', 'reservations_materielles.fournisseur_id', '=', 'users.id')
            ->select(
                'users.id as fournisseur_id',
                'users.name as fournisseur_name',
                'users.ville',
                DB::raw('COUNT(*) as number_of_reservations')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderByDesc('number_of_reservations')
            ->take(5)
            ->get(),

        'lowestRatedFournisseurs' => Feedbacks::join('users', 'feedbacks.target_id', '=', 'users.id')
            ->where('users.role_id', 4) 
            ->select(
                'users.id as fournisseur_id',
                'users.name as fournisseur_name',
                'users.ville',
                DB::raw('AVG(feedbacks.note) as avg_rating'),
                DB::raw('COUNT(feedbacks.id) as total_feedbacks')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderBy('avg_rating', 'asc')
            ->take(5) 
            ->get(),

        'fournisseursWithLowestActivity' => Reservations_materielles::join('users', 'reservations_materielles.fournisseur_id', '=', 'users.id')
            ->select(
                'users.id as fournisseur_id',
                'users.name as fournisseur_id_name',
                'users.ville',
                DB::raw('COUNT(*) as number_of_reservations')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderBy('number_of_reservations', 'asc')
            ->take(5)
            ->get(),

        // guides Stats
        'topRatedGuides' => Feedbacks::join('users', 'feedbacks.target_id', '=', 'users.id')
            ->where('users.role_id', 5) 
            ->select(
                'users.id as guide_id',
                'users.name as guide_name',
                'users.ville',
                DB::raw('AVG(feedbacks.note) as avg_rating'),
                DB::raw('COUNT(feedbacks.id) as total_feedbacks')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderByDesc('avg_rating')
            ->take(5) 
            ->get(),

        'lowestRatedGuides' => Feedbacks::join('users', 'feedbacks.target_id', '=', 'users.id')
            ->where('users.role_id', 5) 
            ->select(
                'users.id as guide_id',
                'users.name as guide_name',
                'users.ville',
                DB::raw('AVG(feedbacks.note) as avg_rating'),
                DB::raw('COUNT(feedbacks.id) as total_feedbacks')
            )
            ->groupBy('users.id', 'users.name', 'users.ville')
            ->orderBy('avg_rating', 'asc')
            ->take(5) 
            ->get(),
        'topTenCampers' => User::where('role_id', 1) // Only campers
            ->select(
                'users.id',
                'users.name',
                DB::raw('(SELECT COUNT(*) FROM reservations_centre WHERE reservations_centre.user_id = users.id) as nbr_reservation_center'),
                DB::raw('(SELECT COUNT(*) FROM reservation_guides WHERE reservation_guides.reserver_id = users.id) as nbr_reservation_guides'),
                DB::raw('(SELECT COUNT(*) FROM reservations_events WHERE reservations_events.user_id = users.id) as nbr_reservation_events'),
                DB::raw('(SELECT COUNT(*) FROM reservations_materielles WHERE reservations_materielles.user_id = users.id) as nbr_reservation_materielles'),
                DB::raw('(
                    (SELECT COUNT(*) FROM reservations_centre WHERE reservations_centre.user_id = users.id) +
                    (SELECT COUNT(*) FROM reservation_guides WHERE reservation_guides.reserver_id = users.id) +
                    (SELECT COUNT(*) FROM reservations_events WHERE reservations_events.user_id = users.id) +
                    (SELECT COUNT(*) FROM reservations_materielles WHERE reservations_materielles.user_id = users.id)
                ) as total_reservations')
            )
            ->orderByDesc('total_reservations')
            ->take(10)
            ->get(),

    ];

    }

    /**
     * Return reservation trends over time (monthly/yearly).
     */
    public function getReservationTrends()
    {
        $currentYear = now()->year;
        $previousYear = now()->subYear()->year;

        // Yearly totals
        $currentYearTotal = Reservations_centre::whereYear('created_at', $currentYear)->count();
        $previousYearTotal = Reservations_centre::whereYear('created_at', $previousYear)->count();

        $growthRate = $previousYearTotal > 0
            ? round((($currentYearTotal - $previousYearTotal) / $previousYearTotal) * 100, 2) . '%'
            : 'N/A';

        // Monthly reservations
        $monthlyReservations = Reservations_centre::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total_reservations')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Top months (2 highest)
        $topMonths = Reservations_centre::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total_reservations')
            )
            ->groupBy('month')
            ->orderByDesc('total_reservations')
            ->take(2)
            ->get();

        // Latest & previous month
        $latestMonth = $monthlyReservations->last();
        $previousMonth = $monthlyReservations->count() > 1 ? $monthlyReservations[$monthlyReservations->count() - 2] : null;

        $vsPrevious = ($previousMonth && $previousMonth->total_reservations > 0)
            ? round((($latestMonth->total_reservations - $previousMonth->total_reservations) / $previousMonth->total_reservations) * 100, 2) . '%'
            : 'N/A';

        return [
            'monthlyCenterReservation' => $monthlyReservations,

            'kpis' => [
                'yearToDate' => [
                    'total_reservations' => $currentYearTotal,
                    'previous_year' => $previousYearTotal,
                    'growth_rate' => $growthRate,
                ],
                'topMonths' => $topMonths,
                'latestMonth' => [
                    'month' => $latestMonth->month ?? null,
                    'total_reservations' => $latestMonth->total_reservations ?? 0,
                    'vs_previous_month' => $vsPrevious,
                ],
            ]
        ];
    }

    public function centerReservationTrends($centerId)
    {
        $currentYear = now()->year;
        $previousYear = now()->subYear()->year;

        // Yearly totals for the center
        $currentYearTotal = Reservations_centre::where('centre_id', $centerId)
            ->whereYear('created_at', $currentYear)
            ->count();

        $previousYearTotal = Reservations_centre::where('centre_id', $centerId)
            ->whereYear('created_at', $previousYear)
            ->count();

        $growthRate = $previousYearTotal > 0
            ? round((($currentYearTotal - $previousYearTotal) / $previousYearTotal) * 100, 2) . '%'
            : 'N/A';

        // Monthly reservations for the center
        $monthlyReservations = Reservations_centre::where('centre_id', $centerId)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total_reservations')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Top months (2 highest) for the center
        $topMonths = Reservations_centre::where('centre_id', $centerId)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total_reservations')
            )
            ->groupBy('month')
            ->orderByDesc('total_reservations')
            ->take(2)
            ->get();

        // Latest & previous month
        $latestMonth = $monthlyReservations->last();
        $previousMonth = $monthlyReservations->count() > 1
            ? $monthlyReservations[$monthlyReservations->count() - 2]
            : null;

        $vsPrevious = ($previousMonth && $previousMonth->total_reservations > 0)
            ? round((($latestMonth->total_reservations - $previousMonth->total_reservations) / $previousMonth->total_reservations) * 100, 2) . '%'
            : 'N/A';

        // Add counts by status
        $statusCounts = Reservations_centre::where('centre_id', $centerId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status'); // gives ['pending' => 10, 'approved' => 5, ...]

        return [
            'monthlyCenterReservation' => $monthlyReservations,

            'kpis' => [
                'yearToDate' => [
                    'total_reservations' => $currentYearTotal,
                    'previous_year' => $previousYearTotal,
                    'growth_rate' => $growthRate,
                ],
                'topMonths' => $topMonths,
                'latestMonth' => [
                    'month' => $latestMonth->month ?? null,
                    'total_reservations' => $latestMonth->total_reservations ?? 0,
                    'vs_previous_month' => $vsPrevious,
                ],
                'byStatus' => [
                    'pending' => $statusCounts['pending'] ?? 0,
                    'approved' => $statusCounts['approved'] ?? 0,
                    'rejected' => $statusCounts['rejected'] ?? 0,
                    'cancelled' => $statusCounts['cancelled'] ?? 0,
                ]
            ]
        ];
    }

    /**
    * return the satisfaction rate0 of a center
     **/
    public function getCenterSatisfaction($centreId)
    {
        // Filter feedbacks related to this center
        $feedbacks = Feedbacks::where('target_id', $centreId)->get();

        if ($feedbacks->isEmpty()) {
            return [
                'average_rating' => null,
                'total_feedbacks' => 0,
                'status_count' => [
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ],
            ];
        }

        $averageRating = round($feedbacks->avg('note'), 2);
        $totalFeedbacks = $feedbacks->count();

        $statusCount = $feedbacks->groupBy('status')->map->count()->toArray();
        // Ensure all statuses are present even if zero
        $statusCount = array_merge(['pending' => 0, 'approved' => 0, 'rejected' => 0], $statusCount);

        return [
            'average_rating' => $averageRating,
            'total_feedbacks' => $totalFeedbacks,
            'status_count' => $statusCount,
        ];
    }


}

