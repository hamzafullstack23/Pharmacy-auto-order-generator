<!-- resources/views/products/upload.blade.php -->
@extends('layouts.app')

@section('title', 'Import Products')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6 lg:p-8">
                <h1 class="text-2xl font-bold mb-6">Import Products from CSV</h1>

                @if(session('import_stats'))
                    <div class="bg-gray-50 border border-gray-300 rounded-lg p-4 mb-6 font-mono text-sm whitespace-pre-wrap">
                        {{ session('import_stats') }}
                    </div>
                @endif

                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <p class="text-green-800">{{ session('success') }}</p>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <p class="text-red-800">{{ session('error') }}</p>
                    </div>
                @endif

                @if(session('info'))
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <p class="text-blue-800">{{ session('info') }}</p>
                    </div>
                @endif

                <!-- Progress Bar Section -->
                <div id="progress-section" class="{{ session('import_id') ? '' : 'hidden' }} mb-6">
                    <div class="bg-white border border-gray-300 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-semibold text-gray-700">Import Progress</h4>
                            <button id="cancel-import" class="text-red-600 hover:text-red-800 text-sm">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </button>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                            <div id="progress-bar" class="bg-indigo-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span id="progress-text">0%</span>
                            <span id="rows-processed">0 / 0 rows</span>
                            <span id="chunk-info">Chunk: 0 / 0</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <div>
                                <span class="text-gray-500">Created:</span>
                                <span id="created-count" class="font-semibold text-green-600">0</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Updated:</span>
                                <span id="updated-count" class="font-semibold text-blue-600">0</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Errors:</span>
                                <span id="errors-count" class="font-semibold text-red-600">0</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Warnings:</span>
                                <span id="warnings-count" class="font-semibold text-yellow-600">0</span>
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div>
                                <span class="text-gray-500">Suppliers:</span>
                                <span id="suppliers-count" class="font-semibold text-purple-600">0</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Companies:</span>
                                <span id="companies-count" class="font-semibold text-orange-600">0</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Duplicates:</span>
                                <span id="duplicates-count" class="font-semibold text-red-600">0</span>
                            </div>
                        </div>
                        <div id="error-message" class="mt-2 text-red-600 text-sm hidden"></div>
                        <div id="status-message" class="mt-2 text-gray-600 text-sm"></div>
                    </div>
                </div>

                <!-- Form Section -->
                <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="space-y-6" id="import-form">
                    @csrf
                    
                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-2">CSV File *</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-500 transition">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                        <span>Upload a file</span>
                                        <input id="file" name="file" type="file" class="sr-only" accept=".csv" required>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">CSV file up to 10MB</p>
                            </div>
                        </div>
                        @error('file')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="dry_run" id="dry_run" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="dry_run" class="ml-2 block text-sm text-gray-900">
                                Dry run (validate only, don't save)
                            </label>
                        </div>
                        <div>
                            <label for="chunk_size" class="block text-sm text-gray-700 mb-1">Chunk Size</label>
                            <select name="chunk_size" id="chunk_size" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="50">50 rows per chunk</option>
                                <option value="100" selected>100 rows per chunk</option>
                                <option value="200">200 rows per chunk</option>
                                <option value="500">500 rows per chunk</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Smaller chunks use less memory but take longer</p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('dashboard') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" id="import-btn" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-upload mr-2"></i> Import Products
                        </button>
                    </div>
                </form>

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Need to import via command line?</h4>
                    <code class="bg-gray-100 px-3 py-2 rounded text-sm text-gray-800 block">
                        php artisan products:import /path/to/your/file.csv --chunk=100
                    </code>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('import-form');
    const importBtn = document.getElementById('import-btn');
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const rowsProcessed = document.getElementById('rows-processed');
    const chunkInfo = document.getElementById('chunk-info');
    const createdCount = document.getElementById('created-count');
    const updatedCount = document.getElementById('updated-count');
    const errorsCount = document.getElementById('errors-count');
    const warningsCount = document.getElementById('warnings-count');
    const suppliersCount = document.getElementById('suppliers-count');
    const companiesCount = document.getElementById('companies-count');
    const duplicatesCount = document.getElementById('duplicates-count');
    const errorMessage = document.getElementById('error-message');
    const statusMessage = document.getElementById('status-message');
    const cancelBtn = document.getElementById('cancel-import');

    let progressInterval = null;
    let importId = '{{ session('import_id') }}';
    let isPolling = false;

    // If there's an import_id from session, start polling
    if (importId) {
        progressSection.classList.remove('hidden');
        startPolling(importId);
    }

    form.addEventListener('submit', function(e) {
        // Show progress section immediately
        progressSection.classList.remove('hidden');
        importBtn.disabled = true;
        importBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Importing...';
        statusMessage.textContent = 'Starting import...';
    });

    function startPolling(id) {
        if (progressInterval) {
            clearInterval(progressInterval);
        }
        isPolling = true;
        progressInterval = setInterval(function() {
            fetchProgress(id);
        }, 2000);
    }

    function fetchProgress(id) {
        fetch('{{ route("products.import.progress") }}?import_id=' + id + '&_=' + Date.now())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Update progress bar
                const progress = data.progress || 0;
                progressBar.style.width = progress + '%';
                progressText.textContent = progress + '%';
                
                // Update stats
                rowsProcessed.textContent = `${data.processed_rows || 0} / ${data.total_rows || 0} rows`;
                chunkInfo.textContent = `Chunk: ${data.current_chunk || 0} / ${data.total_chunks || 0}`;
                createdCount.textContent = data.created || 0;
                updatedCount.textContent = data.updated || 0;
                errorsCount.textContent = data.errors || 0;
                warningsCount.textContent = data.warnings || 0;
                suppliersCount.textContent = data.suppliers_created || 0;
                companiesCount.textContent = data.companies_created || 0;
                duplicatesCount.textContent = data.duplicates_skipped || 0;

                // Update status
                if (data.error) {
                    errorMessage.classList.remove('hidden');
                    errorMessage.textContent = 'Error: ' + data.error;
                }

                if (data.progress > 0 && data.progress < 100) {
                    statusMessage.textContent = 'Processing... ' + data.processed_rows + ' of ' + data.total_rows + ' rows';
                }

                // If complete, stop polling
                if (data.is_complete) {
                    isPolling = false;
                    clearInterval(progressInterval);
                    progressInterval = null;
                    importBtn.disabled = false;
                    importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i> Import Products';
                    statusMessage.textContent = 'Import complete!';
                    
                    // Reload page to show results after a delay
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error fetching progress:', error);
                // Don't stop polling on error
            });
    }

    // Cancel import
    cancelBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel the import?')) {
            fetch('{{ route("products.import.cancel") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ import_id: importId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                    isPolling = false;
                    importBtn.disabled = false;
                    importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i> Import Products';
                    statusMessage.textContent = 'Import cancelled';
                    progressSection.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Error cancelling import:', error);
            });
        }
    });

    // Clean up interval on page unload
    window.addEventListener('beforeunload', function() {
        if (progressInterval) {
            clearInterval(progressInterval);
        }
    });

    // Check for import_id in the URL
    function checkForImportId() {
        const urlParams = new URLSearchParams(window.location.search);
        const importIdFromUrl = urlParams.get('import_id');
        if (importIdFromUrl) {
            importId = importIdFromUrl;
            progressSection.classList.remove('hidden');
            startPolling(importId);
        }
    }
    checkForImportId();
});
</script>
@endpush
@endsection