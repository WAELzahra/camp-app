<?php

namespace App\Repositories;

class DashboardRepository
{
    /**
     * Return statistics about users (e.g. total, active, by role).
     */
    public function userStats()
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
    public function eventStats()
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
    public function profileStats()
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

    ];

    }

    /**
     * Return reservation trends over time (monthly/yearly).
     */
    public function reservationTrends()
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
}

