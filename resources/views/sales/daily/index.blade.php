@extends('layouts.app')

@section('title', 'Daily Sales')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Daily Sales</h1>
            <div class="flex items-center space-x-3">
                <a href="{{ route('sales.daily.export-form') }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    <i class="fas fa-file-export mr-2"></i> Export Sales
                </a>
                <a href="{{ route('sales.daily.export-history') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    <i class="fas fa-history mr-2"></i> Export History
                </a>
            </div>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-xs text-gray-500 uppercase">Total Rows</div>
                <div class="text-2xl font-bold">{{ number_format($totalRows) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-xs text-gray-500 uppercase">Total Quantity</div>
                <div class="text-2xl font-bold">{{ number_format($totalQty, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-xs text-gray-500 uppercase">Un-exported Quantity</div>
                <div class="text-2xl font-bold text-orange-600">{{ number_format($unexportedQty, 2) }}</div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('sales.daily') }}"
            class="bg-white rounded-lg shadow p-4 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                <select name="supplier_id" class="w-full border rounded p-2 text-sm">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(request('supplier_id')==$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full border rounded p-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full border rounded p-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Export Status</label>
                <select name="export_status" class="w-full border rounded p-2 text-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(request('export_status')==='pending' )>Un-exported</option>
                    <option value="exported" @selected(request('export_status')==='exported' )>Exported</option>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">Filter</button>
                <a href="{{ route('sales.daily') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">Clear</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Export</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse($sales as $row)
                    <tr>
                        <td class="px-4 py-2">{{ optional($row->sale_date)->format('Y-m-d') }}</td>
                        <td class="px-4 py-2 font-mono">{{ $row->product_code }}</td>
                        <td class="px-4 py-2">{{ $row->product_name }}</td>
                        <td class="px-4 py-2">{{ $row->supplier->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $row->company->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ number_format($row->quantity, 2) }}</td>
                        <td class="px-4 py-2">
                            @if($row->is_exported)
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i> Exported
                                </span>
                                <form method="POST"
                                    action="{{ route('sales.daily.mark-not-received', $row) }}"
                                    onsubmit="return confirm('Return this product to pending? It will appear in the next export.');">
                                    @csrf
                                    <button class="text-xs text-orange-600 hover:text-orange-800">
                                        <i class="fas fa-undo mr-1"></i> Not received
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                Pending
                            </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $sales->links() }}</div>
    </div>
</div>
@endsection