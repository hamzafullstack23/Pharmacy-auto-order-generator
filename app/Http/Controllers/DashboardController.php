<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\Medicine;
use App\Models\DailySale;
use App\Models\AccumulatedSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_suppliers' => Supplier::count(),
            'total_medicines' => Medicine::count(),
            'pending_orders' => Order::where('status', 'draft')->count(),
            'total_orders' => Order::count(),
            'today_sales' => DailySale::whereDate('sale_date', Carbon::today())->sum('quantity_sold'),
        ];

        $recentOrders = Order::with(['supplier', 'user'])
                            ->latest()
                            ->take(10)
                            ->get();

        $lowStockMedicines = Medicine::whereColumn('current_stock', '<=', 'max_stock_limit')
                                     ->where('max_stock_limit', '>', 0)
                                     ->take(10)
                                     ->get();

        $suppliers = Supplier::where('is_active', true)->get();

        return view('dashboard.index', compact('stats', 'recentOrders', 'lowStockMedicines', 'suppliers'));
    }
}