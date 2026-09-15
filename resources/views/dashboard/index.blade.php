<!-- resources/views/dashboard/index.blade.php -->
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6 lg:p-8">
                <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <div class="text-blue-600 text-sm font-medium">Total Suppliers</div>
                        <div class="text-3xl font-bold">{{ $stats['total_suppliers'] }}</div>
                    </div>
                    <div class="bg-green-50 p-6 rounded-lg">
                        <div class="text-green-600 text-sm font-medium">Total Medicines</div>
                        <div class="text-3xl font-bold">{{ $stats['total_medicines'] }}</div>
                    </div>
                    <div class="bg-yellow-50 p-6 rounded-lg">
                        <div class="text-yellow-600 text-sm font-medium">Pending Orders</div>
                        <div class="text-3xl font-bold">{{ $stats['pending_orders'] }}</div>
                    </div>
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <div class="text-purple-600 text-sm font-medium">Total Orders</div>
                        <div class="text-3xl font-bold">{{ $stats['total_orders'] }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Orders -->
                    <div class="bg-white border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Recent Orders</h2>
                        <div class="space-y-3">
                            @forelse($recentOrders as $order)
                                <div class="flex justify-between items-center border-b pb-2">
                                    <div>
                                        <div class="font-medium">{{ $order->order_number }}</div>
                                        <div class="text-sm text-gray-600">{{ $order->supplier->name }}</div>
                                    </div>
                                    <div>
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            @if($order->status == 'draft') bg-yellow-100 text-yellow-800
                                            @elseif($order->status == 'sent') bg-blue-100 text-blue-800
                                            @elseif($order->status == 'received') bg-green-100 text-green-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ $order->status_label }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500">No recent orders</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Low Stock Medicines -->
                    <div class="bg-white border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Low Stock Medicines</h2>
                        <div class="space-y-3">
                            @forelse($lowStockMedicines as $medicine)
                                <div class="flex justify-between items-center border-b pb-2">
                                    <div>
                                        <div class="font-medium">{{ $medicine->name }}</div>
                                        <div class="text-sm text-gray-600">Stock: {{ $medicine->current_stock }} / {{ $medicine->max_stock_limit }}</div>
                                    </div>
                                    <div class="text-red-600">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500">All medicines are well stocked</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-2 md:grid-cols-3 gap-4">
                    <a href="{{ route('sales.upload') }}" class="bg-indigo-600 text-white p-4 rounded-lg text-center hover:bg-indigo-700">
                        <i class="fas fa-upload text-2xl mb-2 block"></i>
                        Import Sales
                    </a>
                    <a href="{{ route('orders.create') }}" class="bg-green-600 text-white p-4 rounded-lg text-center hover:bg-green-700">
                        <i class="fas fa-plus-circle text-2xl mb-2 block"></i>
                        Create Order
                    </a>
                    <a href="{{ route('suppliers.create') }}" class="bg-purple-600 text-white p-4 rounded-lg text-center hover:bg-purple-700">
                        <i class="fas fa-truck text-2xl mb-2 block"></i>
                        Add Supplier
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection