@extends('layouts.app')

@section('title', 'Import Sales')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6 lg:p-8">
                <h1 class="text-2xl font-bold mb-6">Import Daily Sales</h1>

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('warning'))
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                        <strong>{{ session('warning') }}</strong>
                        @if(session('imported_count'))
                            <div class="mt-2">
                                <span class="text-green-600">✓ Imported: {{ session('imported_count') }}</span>
                                @if(session('skipped_count'))
                                    <span class="text-red-600 ml-4">✗ Skipped: {{ session('skipped_count') }}</span>
                                @endif
                                @if(session('created_count'))
                                    <span class="text-blue-600 ml-4">✓ New Products: {{ session('created_count') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if(session('import_errors'))
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <h3 class="text-red-700 font-semibold mb-2">
                            Import Errors ({{ session('total_errors', count(session('import_errors'))) }} total):
                        </h3>
                        <ul class="list-disc list-inside text-sm text-red-600 max-h-60 overflow-y-auto">
                            @foreach(session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @if(session('total_errors', 0) > count(session('import_errors')))
                                <li class="text-gray-500">... and {{ session('total_errors') - count(session('import_errors')) }} more errors</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="text-blue-700 font-semibold mb-2">Required CSV/Excel Columns:</h3>
                    <ul class="list-disc list-inside text-sm text-blue-600">
                        <li><strong>Product Code</strong> - Must match product code in system (will auto-create if not found)</li>
                        <li><strong>Product Name</strong> - Product name from export</li>
                        <li><strong>Quantity</strong> - Quantity sold</li>
                        <li><strong>Date</strong> (optional) - Sale date (defaults to selected date)</li>
                    </ul>
                    <p class="text-sm text-blue-600 mt-2">
                        Note: Missing products will be automatically created in the system.
                    </p>
                </div>

                <form action="{{ route('sales.upload.post') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    
                    <div>
                        <label for="sale_date" class="block text-sm font-medium text-gray-700">Sale Date</label>
                        <input type="date" name="sale_date" id="sale_date" 
                               value="{{ old('sale_date', date('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @error('sale_date')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700">Excel/CSV File</label>
                        <input type="file" name="file" id="file" 
                               accept=".csv,.xlsx,.xls,.xlsm,.xlsb"
                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-500">Supported formats: CSV, XLSX, XLS (Max size: 100MB)</p>
                        @error('file')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center space-x-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <i class="fas fa-upload mr-2"></i> Import Sales
                        </button>
                        <a href="{{ route('sales.history') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            <i class="fas fa-history mr-2"></i> View History
                        </a>
                    </div>
                </form>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold mb-4">Recent Imports</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse(\App\Models\DailySale::with(['company', 'medicine'])->latest()->take(10)->get() as $sale)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $sale->sale_date->format('Y-m-d') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $sale->medicine->name ?? $sale->medicine_name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($sale->quantity_sold, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $sale->company->name ?? 'N/A' }}
                                            @if(!$sale->company_id)
                                                <span class="text-xs text-gray-400">(No company)</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No imports yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection