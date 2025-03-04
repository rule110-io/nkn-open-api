<?php

namespace App\Http\Controllers;

use App\DailyActiveWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DailyActiveWalletController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date'
        ]);

        $query = DailyActiveWallet::query();

        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::today();

        $threeMonthsAgo = $endDate->copy()->subMonths(3);
        $startDate = $request->start_date ?
            Carbon::parse($request->start_date)->max($threeMonthsAgo) :
            $threeMonthsAgo;
        $query->where('date', '>=', $startDate)
              ->where('date', '<=', $endDate);
        return $query->orderBy('date', 'desc')
            ->get(['date', 'count']);
    }
}
