{{-- resources/views/sales/daily/export-detail.blade.php --}}
@extends('layouts.app')

@section('title', 'Export Detail')

@section('content')
<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Export Detail</h1>
                <p class="text-sm text-gray-500">
                    {{ $batch->supplier_name }} — {{ $batch->created_at->format('Y-m-d H:i:s') }}
                </p>
            </div>
            <a href="{{ route('sales.daily.export-history') }}" class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Back to History
            </a>
        </div>

        <form method="POST" action="{{ route('sales.daily.export-batch.mark-not-received', $batch->export_uuid) }}">
            @csrf
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm text-gray-600">
                        Check products that <strong>did not arrive</strong>. They will be returned to the pending pool.
                    </p>
                    <div>
                        <input type="text" name="reason" placeholder="Reason (optional)"
                               class="border rounded p-1 text-sm mr-2">
                        <button type="submit"
                                class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm">
                            <i class="fas fa-undo mr-2"></i> Mark Selected as Not Received
                        </button>
                    </div>
                </div>

                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 w-8"></th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            <tr>
                                <td class="px-3 py-2">
                                    @if($row->is_exported && $row->export_batch_id === $batch->id)
                                        <input type="checkbox" name="row_ids[]" value="{{ $row->id }}">
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $row->product_name }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row->quantity, 2) }}</td>
                                <td class="px-3 py-2">
                                    @if($row->export_batch_id === $batch->id)
                                        <span class="text-green-700 text-xs">In this export</span>
                                    @else
                                        <span class="text-gray-400 text-xs">Returned</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>
@endsection