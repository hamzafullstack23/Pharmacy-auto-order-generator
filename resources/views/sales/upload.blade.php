@extends('layouts.app')

@section('title', 'Import Sales')

@section('content')
<div class="py-12" x-data="salesImport()" x-cloak>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 lg:p-8">
            <h1 class="text-2xl font-bold mb-6">Import Daily Sales</h1>

            {{-- PHASE 1: UPLOAD --}}
            <template x-if="phase === 'upload'">
                <div class="space-y-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-blue-700 font-semibold mb-2">Required CSV/Excel Columns:</h3>
                        <ul class="list-disc list-inside text-sm text-blue-600">
                            <li><strong>Product Code</strong> — Must match product code in system</li>
                            <li><strong>Product Name</strong> — Product name from export</li>
                            <li><strong>Quantity</strong> — Quantity sold</li>
                            <li><strong>Date</strong> (optional) — Sale date (defaults to selected date)</li>
                        </ul>
                    </div>

                    <form @submit.prevent="submitUpload" enctype="multipart/form-data" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sale Date</label>
                            <input type="date" x-model="saleDate"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Excel/CSV File</label>
                            <input type="file" x-ref="file" accept=".csv,.xlsx,.xls,.xlsm,.xlsb"
                                   class="mt-1 block w-full text-sm">
                        </div>

                        <div class="flex items-center space-x-4">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="loading">
                                <i class="fas fa-upload mr-2"></i>
                                <span x-text="loading ? 'Processing…' : 'Import Sales'"></span>
                            </button>
                            <a href="{{ route('sales.history') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                <i class="fas fa-history mr-2"></i> View History
                            </a>
                        </div>
                    </form>

                    <template x-if="errors.length">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 class="text-red-700 font-semibold mb-2">Errors:</h3>
                            <ul class="list-disc list-inside text-sm text-red-600 max-h-60 overflow-y-auto">
                                <template x-for="(err, i) in errors" :key="i">
                                    <li x-text="err"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </template>

            {{-- PHASE 2: PAUSE & RESOLVE --}}
            <template x-if="phase === 'paused'">
                <div class="space-y-8">
                    <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                        <h2 class="font-semibold text-yellow-800 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Action Required
                        </h2>
                        <p class="text-sm text-yellow-700 mt-1" x-text="pauseMessage"></p>
                    </div>

                    {{-- Unlinked Medicines --}}
                    <template x-if="unlinkedMedicines.length">
                        <div>
                            <h3 class="font-semibold mb-2 text-lg text-blue-700">Assign Supplier to Medicine</h3>
                            <p class="text-sm text-gray-600 mb-3">
                                These medicines have no supplier linked. Pick one.
                            </p>
                            <template x-for="m in unlinkedMedicines" :key="m.medicine_id">
                                <div class="flex flex-wrap items-center gap-3 mb-2 bg-blue-50 p-3 rounded">
                                    <span class="text-sm font-mono" x-text="'Med #' + m.medicine_id"></span>
                                    <span class="text-sm font-medium" x-text="m.medicine_name || m.product_code"></span>
                                    <span class="text-xs text-gray-500" x-text="'(' + m.occurrences + ' rows)'"></span>
                                    <select x-model="resolutions.medicine_suppliers[m.medicine_id]"
                                            class="border rounded p-1 text-sm min-w-[240px]">
                                        <option value="">-- Choose supplier --</option>
                                        <template x-for="s in allSuppliers" :key="s.id">
                                            <option :value="s.id" x-text="s.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Suppliers Without Company --}}
                    <template x-if="suppliersWithoutCompany.length">
                        <div>
                            <h3 class="font-semibold mb-2 text-lg text-orange-700">Suppliers Without a Company</h3>
                            <p class="text-sm text-gray-600 mb-3">
                                Pick an existing company to reassign, or create a new one.
                            </p>
                            <template x-for="s in suppliersWithoutCompany" :key="s.supplier_id">
                                <div class="bg-orange-50 p-3 rounded mb-2">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-sm font-mono" x-text="'Supplier #' + s.supplier_id"></span>
                                        <span class="text-sm font-medium" x-text="s.supplier_name"></span>
                                        <span class="text-xs text-gray-500" x-text="'(' + s.occurrences + ' rows)'"></span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3 mt-2">
                                        <select x-model="resolutions.supplier_company_links[s.supplier_id]"
                                                class="border rounded p-1 text-sm min-w-[240px]">
                                            <option value="">-- Reassign existing company --</option>
                                            <template x-for="c in allCompanies" :key="c.id">
                                                <option :value="c.id" x-text="c.name"></option>
                                            </template>
                                        </select>
                                        <span class="text-xs text-gray-500">or</span>
                                        <input type="text" placeholder="New company name"
                                               x-model="newCompanies[s.supplier_id].name"
                                               class="border rounded p-1 text-sm">
                                        <button @click="createCompanyForSupplier(s.supplier_id)"
                                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            + Create
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Missing Suppliers --}}
                    <template x-if="missingSuppliers.length">
                        <div>
                            <h3 class="font-semibold mb-2 text-lg text-red-700">Missing Suppliers</h3>
                            <template x-for="s in missingSuppliers" :key="s.supplier_id">
                                <div class="flex flex-wrap items-center gap-3 mb-2 bg-red-50 p-3 rounded">
                                    <span class="text-sm font-mono" x-text="'Supplier #' + s.supplier_id"></span>
                                    <span class="text-xs text-gray-500" x-text="'(' + s.occurrences + ' rows)'"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Affected Rows --}}
                    <template x-if="affectedRows.length">
                        <details class="bg-gray-50 border rounded-lg p-3">
                            <summary class="cursor-pointer font-semibold text-sm">
                                <span x-text="affectedRows.length + ' affected rows (click to expand)'"></span>
                            </summary>
                            <div class="overflow-x-auto mt-3 max-h-60">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-2 py-1 text-left">Row</th>
                                            <th class="px-2 py-1 text-left">Code</th>
                                            <th class="px-2 py-1 text-left">Name</th>
                                            <th class="px-2 py-1 text-left">Qty</th>
                                            <th class="px-2 py-1 text-left">Date</th>
                                            <th class="px-2 py-1 text-left">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="r in affectedRows" :key="r.row_id">
                                            <tr>
                                                <td class="px-2 py-1" x-text="r.row_number"></td>
                                                <td class="px-2 py-1 font-mono" x-text="r.product_code"></td>
                                                <td class="px-2 py-1" x-text="r.product_name"></td>
                                                <td class="px-2 py-1" x-text="r.quantity"></td>
                                                <td class="px-2 py-1" x-text="r.sale_date"></td>
                                                <td class="px-2 py-1 text-red-600" x-text="r.reason"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </template>

                    <div class="flex items-center space-x-4 pt-4 border-t">
                        <button @click="resume"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                :disabled="loading">
                            <i class="fas fa-play mr-2"></i>
                            <span x-text="loading ? 'Resuming…' : 'Resume Import'"></span>
                        </button>
                        <button @click="reset"
                                class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </button>
                    </div>
                </div>
            </template>

            {{-- PHASE 3: COMPLETED --}}
            <template x-if="phase === 'completed'">
                <div class="space-y-4">
                    <div class="bg-green-50 border border-green-300 rounded-lg p-6">
                        <h2 class="font-semibold text-green-800 text-lg flex items-center">
                            <i class="fas fa-check-circle mr-2"></i> Import Completed
                        </h2>
                        <p class="text-sm text-green-700 mt-2">
                            Imported: <strong x-text="stats.imported || 0"></strong> &middot;
                            Skipped: <strong x-text="stats.skipped || 0"></strong>
                        </p>
                    </div>
                    <button @click="reset"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Import Another File
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function salesImport() {
    return {
        phase: 'upload',
        loading: false,
        saleDate: new Date().toISOString().slice(0, 10),
        batch: null,
        pauseMessage: '',
        missingMedicines: [],
        missingSuppliers: [],
        missingCompanies: [],
        unlinkedMedicines: [],
        suppliersWithoutCompany: [],
        affectedRows: [],
        stats: {},
        errors: [],
        allSuppliers: [],
        allCompanies: [],
        newCompanies: {},
        resolutions: {
            medicine_suppliers: {},
            supplier_company_links: {},
        },

        async submitUpload() {
            this.errors = [];
            const fileInput = this.$refs.file;
            if (!fileInput || !fileInput.files[0]) {
                alert('Please select a file.');
                return;
            }
            this.loading = true;
            const fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('sale_date', this.saleDate);

            try {
                const data = await window.apiFetch('{{ route('sales.upload.post') }}', {
                    method: 'POST',
                    body: fd,
                });
                await this.handleResponse(data);
            } catch (e) {
                this.errors = [e.message];
            } finally {
                this.loading = false;
            }
        },

        async handleResponse(data) {
            if (data.status === 'paused') {
                this.phase = 'paused';
                this.batch = data.batch;
                this.pauseMessage = data.message;
                this.missingMedicines        = data.missing_medicines || [];
                this.missingSuppliers        = data.missing_suppliers || [];
                this.missingCompanies        = data.missing_companies || [];
                this.unlinkedMedicines       = data.unlinked_medicines || [];
                this.suppliersWithoutCompany = data.suppliers_without_company || [];
                this.affectedRows            = data.affected_rows || [];

                this.suppliersWithoutCompany.forEach(s => {
                    if (!this.newCompanies[s.supplier_id]) {
                        this.newCompanies[s.supplier_id] = { name: '' };
                    }
                });

                try {
                    const opts = await window.apiFetch('{{ route('sales.import.options') }}');
                    this.allSuppliers = opts.suppliers || [];
                    this.allCompanies = opts.companies || [];
                } catch (e) {
                    console.error('Failed to load options', e);
                }
            } else if (data.status === 'completed') {
                this.phase = 'completed';
                this.stats = data.stats || {};
            } else {
                this.errors = [data.message || 'Import failed'];
            }
        },

        async resume() {
            this.loading = true;
            try {
                const payload = {
                    medicine_suppliers: this.resolutions.medicine_suppliers,
                    supplier_company_links: this.resolutions.supplier_company_links,
                    new_companies: Object.entries(this.newCompanies)
                        .filter(([_, v]) => v && v.name)
                        .map(([supplierId, v]) => ({
                            name: v.name,
                            supplier_id: parseInt(supplierId),
                        })),
                };

                const url = '{{ url('/sales/import') }}/' + this.batch + '/resume';
                const data = await window.apiFetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                await this.handleResponse(data);
            } catch (e) {
                this.errors = [e.message];
            } finally {
                this.loading = false;
            }
        },

        async createCompanyForSupplier(supplierId) {
            const name = this.newCompanies[supplierId]?.name;
            if (!name) {
                alert('Enter a company name first.');
                return;
            }
            try {
                const company = await window.apiFetch('{{ route('sales.import.companies.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name, supplier_id: supplierId, is_active: true }),
                });
                this.allCompanies.push(company);
                this.newCompanies[supplierId] = { name: '' };
                alert('Company created: ' + company.name);
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },

        reset() {
            Object.assign(this, {
                phase: 'upload', batch: null, pauseMessage: '',
                missingMedicines: [], missingSuppliers: [], missingCompanies: [],
                unlinkedMedicines: [], suppliersWithoutCompany: [],
                affectedRows: [], stats: {}, errors: [],
                allSuppliers: [], allCompanies: [], newCompanies: {},
                resolutions: { medicine_suppliers: {}, supplier_company_links: {} },
            });
            if (this.$refs.file) this.$refs.file.value = '';
        },
    };
}
</script>
@endpush