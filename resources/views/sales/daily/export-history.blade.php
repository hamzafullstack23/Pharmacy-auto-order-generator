@extends('layouts.app')

@section('title', 'Export History')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Export History</h1>
            <a href="{{ route('sales.daily') }}" class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Back to Daily Sales
            </a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Products</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Qty</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse($batches as $batch)
                        <tr>
                            <td class="px-4 py-2">{{ $batch->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2">{{ $batch->supplier_name }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($batch->rows_count) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($batch->total_quantity, 2) }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $batch->filename }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">No exports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $batches->links() }}</div>
    </div>
</div>
@endsection