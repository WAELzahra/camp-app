<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $dashboardRepository;

    public function __construct(DashboardRepository $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    /**
     * Show the main admin dashboard.
     */
    public function index()
    {
        $stats = [
            'userStats' => $this->dashboardRepository->getUserStats(),
            'eventStats' => $this->dashboardRepository->getEventStats(),
            'profileStats' => $this->dashboardRepository->getProfileStats(),
            'reservationTrends' => $this->dashboardRepository->getReservationTrends(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function centerStats()
    {

        $stats = [
            'satisfactionRate'    => $this->dashboardRepository->getCenterSatisfaction($user->id),
            'reservationTrends'  => $this->dashboardRepository->getCenterSatisfaction($user->id)
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $stats
        ]);
    }
}