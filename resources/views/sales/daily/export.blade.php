@extends('layouts.app')

@section('title', 'Export Daily Sales')

@section('content')
<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Export Daily Sales</h1>
            <a href="{{ route('sales.daily') }}" class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Back to Daily Sales
            </a>
        </div>

        @if(session('warning'))
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
            {{ session('warning') }}
        </div>
        @endif

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700">
            <p><strong>Exported rows will be marked as "Exported" and won't appear in future exports.</strong></p>
            <p class="mt-1">The CSV will be headed "Medica Plus Pharmacy LMDC", followed by the supplier name, then a table of Product Name and aggregated Quantity.</p>
        </div>

        <form method="GET" action="{{ route('sales.daily.export-form') }}"
            class="bg-white rounded-lg shadow p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                <select name="supplier_id" class="w-full border rounded p-2 text-sm" required>
                    <option value="">-- Select a supplier to preview --</option>
                    @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected($selectedSupplier && $selectedSupplier->id === $s->id)>
                        {{ $s->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">
                Preview
            </button>
        </form>

        @if($selectedSupplier)
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-lg font-semibold">{{ $selectedSupplier->name }}</h2>
                    <p class="text-xs text-gray-500">
                        Un-exported product rows: {{ $preview->count() }} ·
                        Total quantity: {{ number_format($preview->sum('total_quantity'), 2) }}
                    </p>
                </div>
                @if($preview->isNotEmpty())
                <form method="POST" action="{{ route('sales.daily.export') }}" class="flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">

                    <label class="text-sm font-medium text-gray-700">Format:</label>
                    <select name="format" class="border rounded p-2 text-sm">
                        <option value="xlsx" selected>Excel (.xlsx) — styled</option>
                        <option value="csv">CSV (.csv) — plain text</option>
                    </select>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        <i class="fas fa-download mr-2"></i> Export & Mark as Done
                    </button>
                </form>
                @endif
            </div>

            @if($preview->isEmpty())
            <p class="text-sm text-gray-500 py-8 text-center">No un-exported rows for this supplier.</p>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($preview as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row->product_name }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($row->total_quantity, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-3 py-2 text-right">TOTAL</td>
                            <td class="px-3 py-2 text-right">{{ number_format($preview->sum('total_quantity'), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection