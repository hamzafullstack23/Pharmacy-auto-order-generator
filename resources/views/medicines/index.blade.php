@extends('layouts.app')

@section('title', 'Medicines')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6 lg:p-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">Medicines</h1>
                    <a href="{{ route('medicines.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        <i class="fas fa-plus mr-2"></i> Add Medicine
                    </a>
                </div>

                <!-- Filter Section -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <form method="GET" action="{{ route('medicines.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div>
                            <label for="filter_product_code" class="block text-sm font-medium text-gray-700 mb-1">Product Code</label>
                            <input type="text" name="filter_product_code" id="filter_product_code" 
                                   value="{{ request('filter_product_code') }}" 
                                   placeholder="Search by code..." 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        
                        <div>
                            <label for="filter_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="filter_name" id="filter_name" 
                                   value="{{ request('filter_name') }}" 
                                   placeholder="Search by name..." 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        
                        <div>
                            <label for="filter_unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                            <input type="text" name="filter_unit" id="filter_unit" 
                                   value="{{ request('filter_unit') }}" 
                                   placeholder="Search by unit..." 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        
                        <div>
                            <label for="filter_pack_type" class="block text-sm font-medium text-gray-700 mb-1">Pack Type</label>
                            <select name="filter_pack_type" id="filter_pack_type" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">All Types</option>
                                <option value="strip" {{ request('filter_pack_type') == 'strip' ? 'selected' : '' }}>Strip</option>
                                <option value="bottle" {{ request('filter_pack_type') == 'bottle' ? 'selected' : '' }}>Bottle</option>
                                <option value="box" {{ request('filter_pack_type') == 'box' ? 'selected' : '' }}>Box</option>
                                <option value="vial" {{ request('filter_pack_type') == 'vial' ? 'selected' : '' }}>Vial</option>
                                <option value="syringe" {{ request('filter_pack_type') == 'syringe' ? 'selected' : '' }}>Syringe</option>
                                <option value="other" {{ request('filter_pack_type') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="filter_stock" class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
                            <select name="filter_stock" id="filter_stock" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">All Stock</option>
                                <option value="low" {{ request('filter_stock') == 'low' ? 'selected' : '' }}>Low Stock (&lt; 10)</option>
                                <option value="medium" {{ request('filter_stock') == 'medium' ? 'selected' : '' }}>Medium Stock (10-50)</option>
                                <option value="high" {{ request('filter_stock') == 'high' ? 'selected' : '' }}>High Stock (&gt; 50)</option>
                                <option value="out" {{ request('filter_stock') == 'out' ? 'selected' : '' }}>Out of Stock (0)</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <i class="fas fa-search mr-2"></i> Filter
                            </button>
                            <a href="{{ route('medicines.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                <i class="fas fa-times mr-2"></i> Reset
                            </a>
                        </div>
                    </form>
                    
                    <!-- Active Filters Display -->
                    @if(request()->hasAny(['filter_product_code', 'filter_name', 'filter_unit', 'filter_pack_type', 'filter_stock']))
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="text-sm text-gray-600">Active filters:</span>
                            @if(request('filter_product_code'))
                                <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                    Code: {{ request('filter_product_code') }}
                                    <a href="{{ route('medicines.index', array_merge(request()->query(), ['filter_product_code' => null])) }}" class="ml-1 text-indigo-600 hover:text-indigo-800">&times;</a>
                                </span>
                            @endif
                            @if(request('filter_name'))
                                <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                    Name: {{ request('filter_name') }}
                                    <a href="{{ route('medicines.index', array_merge(request()->query(), ['filter_name' => null])) }}" class="ml-1 text-indigo-600 hover:text-indigo-800">&times;</a>
                                </span>
                            @endif
                            @if(request('filter_unit'))
                                <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                    Unit: {{ request('filter_unit') }}
                                    <a href="{{ route('medicines.index', array_merge(request()->query(), ['filter_unit' => null])) }}" class="ml-1 text-indigo-600 hover:text-indigo-800">&times;</a>
                                </span>
                            @endif
                            @if(request('filter_pack_type'))
                                <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                    Pack Type: {{ ucfirst(request('filter_pack_type')) }}
                                    <a href="{{ route('medicines.index', array_merge(request()->query(), ['filter_pack_type' => null])) }}" class="ml-1 text-indigo-600 hover:text-indigo-800">&times;</a>
                                </span>
                            @endif
                            @if(request('filter_stock'))
                                <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                    Stock: {{ ucfirst(str_replace('_', ' ', request('filter_stock'))) }}
                                    <a href="{{ route('medicines.index', array_merge(request()->query(), ['filter_stock' => null])) }}" class="ml-1 text-indigo-600 hover:text-indigo-800">&times;</a>
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Results Count -->
                <div class="mb-4 text-sm text-gray-600">
                    Showing {{ $medicines->firstItem() ?? 0 }} to {{ $medicines->lastItem() ?? 0 }} of {{ $medicines->total() }} results
                </div>

                @if($medicines->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pack Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($medicines as $medicine)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @if($medicine->product_code)
                                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-md text-xs font-mono">{{ $medicine->product_code }}</span>
                                            @else
                                                <span class="text-gray-400 text-xs">N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $medicine->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $medicine->unit ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($medicine->pack_type) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $medicine->current_stock }} / {{ $medicine->max_stock_limit ?? '∞' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <!-- View Button -->
                                            <button type="button" 
                                                    onclick="showMedicineDetails({{ $medicine->id }})" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <a href="{{ route('medicines.edit', $medicine) }}" class="text-indigo-600 hover:text-indigo-900">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form action="{{ route('medicines.destroy', $medicine) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $medicines->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <p class="text-gray-500">No medicines found matching your filters.</p>
                        <a href="{{ route('medicines.index') }}" class="mt-2 inline-block text-indigo-600 hover:text-indigo-900">Clear all filters</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- View Medicine Modal -->
<div id="medicineModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeModal()"></div>

        <!-- Modal panel -->
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="px-6 pt-6 pb-4 bg-white">
                <div class="flex justify-between items-start">
                    <h3 class="text-xl font-semibold text-gray-900" id="modal-title">
                        Medicine Details
                    </h3>
                    <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <!-- Loading spinner -->
                <div id="modalLoading" class="py-8 text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-600"></i>
                    <p class="mt-2 text-gray-600">Loading details...</p>
                </div>
                
                <!-- Content container -->
                <div id="modalContent" class="mt-4 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Basic Information</h4>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Product Code</dt>
                                    <dd id="modalProductCode" class="mt-1 text-sm text-gray-900 font-mono"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                                    <dd id="modalName" class="mt-1 text-sm text-gray-900 font-semibold"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Unit</dt>
                                    <dd id="modalUnit" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Pack Type</dt>
                                    <dd id="modalPackType" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Pack Size</dt>
                                    <dd id="modalPackSize" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Cost</dt>
                                    <dd id="modalCost" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                            </dl>
                        </div>
                        
                        <!-- Right Column -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Stock & Status</h4>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Current Stock</dt>
                                    <dd id="modalStock" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Max Stock Limit</dt>
                                    <dd id="modalMaxStock" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd id="modalStatus" class="mt-1"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                    <dd id="modalCreatedAt" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                    <dd id="modalUpdatedAt" class="mt-1 text-sm text-gray-900"></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <!-- Company & Supplier Section -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Company & Supplier Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h5 class="text-sm font-semibold text-gray-700 mb-2">Company (Manufacturer)</h5>
                                <dl class="space-y-2 bg-gray-50 p-3 rounded-lg">
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Name</dt>
                                        <dd id="modalCompanyName" class="mt-1 text-sm text-gray-900 font-semibold"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Contact Person</dt>
                                        <dd id="modalCompanyContact" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Email</dt>
                                        <dd id="modalCompanyEmail" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Phone</dt>
                                        <dd id="modalCompanyPhone" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Address</dt>
                                        <dd id="modalCompanyAddress" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-700 mb-2">Supplier</h5>
                                <dl class="space-y-2 bg-gray-50 p-3 rounded-lg">
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Name</dt>
                                        <dd id="modalSupplierName" class="mt-1 text-sm text-gray-900 font-semibold"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Contact Person</dt>
                                        <dd id="modalSupplierContact" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Email</dt>
                                        <dd id="modalSupplierEmail" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Phone</dt>
                                        <dd id="modalSupplierPhone" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Address</dt>
                                        <dd id="modalSupplierAddress" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category & Generic Section -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Category & Generic Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h5 class="text-sm font-semibold text-gray-700 mb-2">Category</h5>
                                <dl class="space-y-2 bg-gray-50 p-3 rounded-lg">
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Category</dt>
                                        <dd id="modalCategory" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Sub Category</dt>
                                        <dd id="modalSubCategory" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-700 mb-2">Generic</h5>
                                <dl class="space-y-2 bg-gray-50 p-3 rounded-lg">
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500">Generic Name</dt>
                                        <dd id="modalGenerics" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 text-right rounded-b-lg">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let modalOpen = false;
    
    function showMedicineDetails(id) {
        modalOpen = true;
        document.getElementById('medicineModal').classList.remove('hidden');
        document.getElementById('modalLoading').classList.remove('hidden');
        document.getElementById('modalContent').classList.add('hidden');
        
        fetch(`/medicines/${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load medicine details');
                }
                return response.json();
            })
            .then(data => {
                console.log('Medicine details:', data);
                populateModal(data);
                document.getElementById('modalLoading').classList.add('hidden');
                document.getElementById('modalContent').classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('modalLoading').innerHTML = `
                    <div class="text-red-600">
                        <i class="fas fa-exclamation-circle text-3xl"></i>
                        <p class="mt-2">Failed to load medicine details. Please try again.</p>
                        <p class="text-sm mt-1">${error.message}</p>
                    </div>
                `;
            });
    }
    
    function populateModal(data) {
        // Basic Information
        document.getElementById('modalProductCode').textContent = data.product_code || 'N/A';
        document.getElementById('modalName').textContent = data.name || 'N/A';
        document.getElementById('modalUnit').textContent = data.unit || 'N/A';
        document.getElementById('modalPackType').textContent = data.pack_type ? ucfirst(data.pack_type) : 'N/A';
        document.getElementById('modalPackSize').textContent = data.pack_size || 'N/A';
        document.getElementById('modalCost').textContent = data.cost !== null ? 'Rs. ' + parseFloat(data.cost).toFixed(2) : 'N/A';
        
        // Stock & Status
        document.getElementById('modalStock').textContent = data.current_stock || 0;
        document.getElementById('modalMaxStock').textContent = data.max_stock_limit || 'No limit';
        
        const statusBadge = document.getElementById('modalStatus');
        if (data.is_active !== undefined && data.is_active !== null) {
            const isActive = data.is_active === 1 || data.is_active === true;
            statusBadge.innerHTML = isActive 
                ? '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>'
                : '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Inactive</span>';
        } else {
            statusBadge.innerHTML = '<span class="text-gray-500">N/A</span>';
        }
        
        document.getElementById('modalCreatedAt').textContent = data.created_at ? new Date(data.created_at).toLocaleString() : 'N/A';
        document.getElementById('modalUpdatedAt').textContent = data.updated_at ? new Date(data.updated_at).toLocaleString() : 'N/A';
        
        // Company Information
        if (data.company) {
            document.getElementById('modalCompanyName').textContent = data.company.name || 'N/A';
            document.getElementById('modalCompanyContact').textContent = data.company.contact_person || 'N/A';
            document.getElementById('modalCompanyEmail').textContent = data.company.email || 'N/A';
            document.getElementById('modalCompanyPhone').textContent = data.company.phone || 'N/A';
            document.getElementById('modalCompanyAddress').textContent = data.company.address || 'N/A';
        } else {
            document.getElementById('modalCompanyName').textContent = 'No company assigned';
            document.getElementById('modalCompanyContact').textContent = 'N/A';
            document.getElementById('modalCompanyEmail').textContent = 'N/A';
            document.getElementById('modalCompanyPhone').textContent = 'N/A';
            document.getElementById('modalCompanyAddress').textContent = 'N/A';
        }
        
        // Supplier Information
        if (data.supplier) {
            document.getElementById('modalSupplierName').textContent = data.supplier.name || 'N/A';
            document.getElementById('modalSupplierContact').textContent = data.supplier.contact_person || 'N/A';
            document.getElementById('modalSupplierEmail').textContent = data.supplier.email || 'N/A';
            document.getElementById('modalSupplierPhone').textContent = data.supplier.phone || 'N/A';
            document.getElementById('modalSupplierAddress').textContent = data.supplier.address || 'N/A';
        } else {
            document.getElementById('modalSupplierName').textContent = 'No supplier assigned';
            document.getElementById('modalSupplierContact').textContent = 'N/A';
            document.getElementById('modalSupplierEmail').textContent = 'N/A';
            document.getElementById('modalSupplierPhone').textContent = 'N/A';
            document.getElementById('modalSupplierAddress').textContent = 'N/A';
        }
        
        // Category & Generic Information
        document.getElementById('modalCategory').textContent = data.category_name || 'N/A';
        document.getElementById('modalSubCategory').textContent = data.sub_category_name || 'N/A';
        document.getElementById('modalGenerics').textContent = data.generics || 'N/A';
    }
    
    function closeModal() {
        modalOpen = false;
        document.getElementById('medicineModal').classList.add('hidden');
        document.getElementById('modalContent').classList.add('hidden');
        document.getElementById('modalLoading').classList.remove('hidden');
    }
    
    function ucfirst(str) {
        if (!str) return str;
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modalOpen) {
            closeModal();
        }
    });
</script>
@endpush