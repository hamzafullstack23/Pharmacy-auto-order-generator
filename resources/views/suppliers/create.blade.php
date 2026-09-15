@extends('layouts.app')

@section('title', 'Add Supplier')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6 lg:p-8">
                <h1 class="text-2xl font-bold mb-6">Add New Supplier</h1>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <ul class="list-disc ml-4">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('import_errors'))
                    <div class="bg-yellow-50 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                        <h4 class="font-bold">Import Errors:</h4>
                        <ul class="list-disc ml-4 mt-2">
                            @foreach(session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- ===== TABS ===== --}}
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="switchTab('single')" id="tab-single" 
                                    class="border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Single Entry
                            </button>
                            <button onclick="switchTab('bulk')" id="tab-bulk"
                                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Bulk Upload (CSV/Excel)
                            </button>
                        </nav>
                    </div>
                </div>

                {{-- ===== SINGLE ENTRY FORM ===== --}}
                <div id="single-form">
                    <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-6">
                        @csrf
                        <!-- ... single form fields ... -->
                    </form>
                </div>

                {{-- ===== BULK UPLOAD FORM ===== --}}
                <div id="bulk-form" style="display: none;">
                    {{-- ===== ADD enctype AND ID TO FORM ===== --}}
                    <form method="POST" action="{{ route('suppliers.store') }}" 
                          enctype="multipart/form-data" 
                          class="space-y-6"
                          id="bulkUploadForm"
                          onsubmit="return validateBulkFile()">
                        @csrf

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-blue-800 mb-2">Bulk Upload Instructions:</h4>
                            <ul class="list-disc ml-4 text-blue-700 text-sm space-y-1">
                                <li>Upload a CSV or Excel file with supplier data</li>
                                <li>Required column: <strong>name</strong></li>
                                <li>Optional columns: contact_person, email, phone, address, order_day</li>
                                <li>Maximum file size: <strong>100MB</strong></li>
                                <li>Duplicate supplier names will be skipped</li>
                            </ul>
                        </div>

                        <div>
                            <label for="bulk_file" class="block text-sm font-medium text-gray-700">Upload File *</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="bulk_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span>Upload a file</span>
                                            {{-- ===== UPDATED accept ATTRIBUTE ===== --}}
                                            <input id="bulk_file" name="bulk_file" type="file" 
                                                   class="sr-only" 
                                                   accept=".csv,.xlsx,.xls,.xlsm,.xlsb"
                                                   onchange="validateFileType(this)">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">CSV, XLSX, or XLS up to 100MB</p>
                                </div>
                            </div>
                            
                            {{-- ===== ERROR DISPLAY ===== --}}
                            <div id="fileError" class="mt-2 text-red-500 text-sm hidden"></div>
                            
                            @error('bulk_file')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                            
                            <div id="fileInfo" class="mt-2 text-sm text-gray-500 hidden">
                                Selected: <span id="fileName"></span> - <span id="fileSize"></span>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('suppliers.index') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" id="submitBtn" class="inline-flex justify-center rounded-md border border-transparent bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                Import Suppliers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ===== COMPLETE FRONTEND VALIDATION =====

const allowedExtensions = ['csv', 'xlsx', 'xls', 'xlsm', 'xlsb'];
const maxFileSize = 100 * 1024 * 1024; // 100MB

function validateFileType(input) {
    const file = input.files[0];
    const fileError = document.getElementById('fileError');
    const submitBtn = document.getElementById('submitBtn');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    // Reset
    fileError.classList.add('hidden');
    fileError.textContent = '';
    submitBtn.disabled = false;
    
    if (!file) {
        fileInfo.classList.add('hidden');
        return;
    }
    
    // Get extension
    const fileNameFull = file.name;
    const extension = fileNameFull.split('.').pop().toLowerCase();
    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
    
    // Show file info
    fileName.textContent = fileNameFull;
    fileSize.textContent = sizeInMB + ' MB';
    fileInfo.classList.remove('hidden');
    
    // ===== VALIDATE EXTENSION =====
    if (!allowedExtensions.includes(extension)) {
        fileError.textContent = `Invalid file type: .${extension}. Allowed types: .csv, .xlsx, .xls, .xlsm, .xlsb`;
        fileError.classList.remove('hidden');
        submitBtn.disabled = true;
        input.value = ''; // Clear the file input
        fileInfo.classList.add('hidden');
        return false;
    }
    
    // ===== VALIDATE FILE SIZE =====
    if (file.size > maxFileSize) {
        fileError.textContent = `File too large: ${sizeInMB}MB. Maximum size is 100MB.`;
        fileError.classList.remove('hidden');
        submitBtn.disabled = true;
        input.value = ''; // Clear the file input
        fileInfo.classList.add('hidden');
        return false;
    }
    
    return true;
}

// ===== FORM SUBMISSION VALIDATION =====
function validateBulkFile() {
    const fileInput = document.getElementById('bulk_file');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a file to upload.');
        return false;
    }
    
    // Validate extension
    const extension = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(extension)) {
        alert(`Invalid file type: .${extension}. Please upload a CSV or Excel file.`);
        return false;
    }
    
    // Validate file size
    if (file.size > maxFileSize) {
        alert(`File too large: ${(file.size / (1024 * 1024)).toFixed(2)}MB. Maximum size is 100MB.`);
        return false;
    }
    
    return true;
}

// ===== TAB SWITCHING =====
function switchTab(tab) {
    document.getElementById('single-form').style.display = 'none';
    document.getElementById('bulk-form').style.display = 'none';
    
    document.getElementById('tab-single').className = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm';
    document.getElementById('tab-bulk').className = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm';
    
    if (tab === 'single') {
        document.getElementById('single-form').style.display = 'block';
        document.getElementById('tab-single').className = 'border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm';
    } else {
        document.getElementById('bulk-form').style.display = 'block';
        document.getElementById('tab-bulk').className = 'border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm';
    }
}

// ===== DRAG AND DROP SUPPORT =====
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.querySelector('.border-dashed');
    const fileInput = document.getElementById('bulk_file');
    
    if (dropZone) {
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-indigo-500', 'bg-indigo-50');
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-indigo-500', 'bg-indigo-50');
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-indigo-500', 'bg-indigo-50');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                validateFileType(fileInput);
            }
        });
    }
});

// Show bulk tab if there are errors from bulk upload
@if($errors->has('bulk_file') || session('import_errors'))
    switchTab('bulk');
@endif
</script>
@endpush
@endsection