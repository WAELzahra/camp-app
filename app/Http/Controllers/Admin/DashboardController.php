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
            'totalUsers' => $this->dashboardRepository->getTotalUsers(),
            'activeUsers' => $this->dashboardRepository->getActiveUsers(),
            'totalCenters' => $this->dashboardRepository->getTotalCenters(),
            'totalEvents' => $this->dashboardRepository->getTotalEvents(),
            'monthlyReservations' => $this->dashboardRepository->getMonthlyReservations(),
            'topProfiles' => $this->dashboardRepository->getTopProfiles(),
        ];

        //return view('admin.dashboard.index', compact('stats'));
        // or return response()->json($stats) if API
    }
}