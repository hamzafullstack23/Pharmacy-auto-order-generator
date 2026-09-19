@extends('layouts.app')

@section('title', 'Import Sales')

@section('content')
<div class="py-12" x-data="salesImport()" x-cloak>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 lg:p-8">
            <h1 class="text-2xl font-bold mb-6">Import Daily Sales</h1>

            {{-- ============ PHASE 1: UPLOAD ============ --}}
            <template x-if="phase === 'upload'">
                <div class="space-y-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-blue-700 font-semibold mb-2">Required CSV/Excel Columns:</h3>
                        <ul class="list-disc list-inside text-sm text-blue-600">
                            <li><strong>Product Code</strong></li>
                            <li><strong>Product Name</strong></li>
                            <li><strong>Quantity</strong></li>
                            <li><strong>Date</strong> (optional)</li>
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

            {{-- ============ PHASE 2: PAUSE & RESOLVE ============ --}}
            <template x-if="phase === 'paused'">
                <div class="space-y-8">
                    <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                        <h2 class="font-semibold text-yellow-800 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Action Required
                        </h2>
                        <p class="text-sm text-yellow-700 mt-1" x-text="pauseMessage"></p>
                    </div>

                    {{-- ===== SINGLE MISSING MEDICINE FORM ===== --}}
                    <template x-if="missingMedicines.length">
                        <div class="bg-white border-2 border-red-300 rounded-lg p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-red-700">Missing Medicine</h3>
                                <span class="text-xs text-gray-500"
                                      x-text="'Item ' + (totalMissingMedicines - missingMedicines.length + 1) + ' of ' + totalMissingMedicines"></span>
                            </div>

                            <div class="bg-red-50 border border-red-200 rounded p-3">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><span class="font-medium">Product Code:</span>
                                        <span class="font-mono ml-2" x-text="missingMedicines[0].product_code"></span></div>
                                    <div><span class="font-medium">Product Name:</span>
                                        <span class="ml-2" x-text="missingMedicines[0].product_name"></span></div>
                                    <div><span class="font-medium">Affected Rows:</span>
                                        <span class="ml-2" x-text="missingMedicines[0].occurrences"></span></div>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600">
                                This product code doesn't exist in the system. Create it now with the correct company and supplier, or skip these rows.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Company --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Company *</label>
                                    <select x-model="newMedicine.company_id"
                                            class="w-full border rounded p-2 text-sm">
                                        <option value="">-- Select company --</option>
                                        <template x-for="c in allCompanies" :key="c.id">
                                            <option :value="c.id" x-text="c.name"></option>
                                        </template>
                                    </select>
                                    <input type="text"
                                           placeholder="Or type new company name"
                                           x-model="newMedicine.company_name"
                                           class="mt-2 w-full border rounded p-1 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Typing a name creates a new company linked to the chosen supplier.
                                    </p>
                                </div>

                                {{-- Supplier --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                                    <select x-model="newMedicine.supplier_id"
                                            class="w-full border rounded p-2 text-sm">
                                        <option value="">-- Select supplier --</option>
                                        <template x-for="s in allSuppliers" :key="s.id">
                                            <option :value="s.id" x-text="s.name"></option>
                                        </template>
                                    </select>
                                    <input type="text"
                                           placeholder="Or type new supplier name"
                                           x-model="newMedicine.supplier_name"
                                           class="mt-2 w-full border rounded p-1 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Typing a name creates a new supplier and links it to this medicine.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3 pt-2 border-t">
                                <button type="button" @click="resolveMedicineAction('create')"
                                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                        :disabled="loading">
                                    <i class="fas fa-plus mr-2"></i> Create & Continue
                                </button>
                                <button type="button" @click="resolveMedicineAction('skip')"
                                        class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 disabled:opacity-50"
                                        :disabled="loading">
                                    <i class="fas fa-forward mr-2"></i> Skip These Rows
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- ===== SINGLE UNLINKED MEDICINE FORM ===== --}}
                    <template x-if="!missingMedicines.length && unlinkedMedicines.length">
                        <div class="bg-white border-2 border-blue-300 rounded-lg p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-blue-700">Assign Supplier to Medicine</h3>
                                <span class="text-xs text-gray-500"
                                      x-text="'Item ' + (totalUnlinkedMedicines - unlinkedMedicines.length + 1) + ' of ' + totalUnlinkedMedicines"></span>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm">
                                <div><span class="font-medium">Medicine:</span>
                                    <span class="ml-2" x-text="unlinkedMedicines[0].medicine_name"></span>
                                    <span class="text-gray-500 ml-2" x-text="'(Med #' + unlinkedMedicines[0].medicine_id + ')'"></span>
                                </div>
                                <div><span class="font-medium">Product Code:</span>
                                    <span class="font-mono ml-2" x-text="unlinkedMedicines[0].product_code"></span></div>
                                <div><span class="font-medium">Affected Rows:</span>
                                    <span class="ml-2" x-text="unlinkedMedicines[0].occurrences"></span></div>
                            </div>

                            <p class="text-sm text-gray-600">
                                This medicine has no supplier linked. Pick one to link it.
                            </p>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                                <select x-model="unlinkedSupplierId"
                                        class="w-full md:w-1/2 border rounded p-2 text-sm">
                                    <option value="">-- Select supplier --</option>
                                    <template x-for="s in allSuppliers" :key="s.id">
                                        <option :value="s.id" x-text="s.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="flex items-center space-x-3 pt-2 border-t">
                                <button type="button" @click="resolveUnlinkedMedicine"
                                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                        :disabled="loading || !unlinkedSupplierId">
                                    <i class="fas fa-link mr-2"></i> Link & Continue
                                </button>
                            </div>
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
                        <button @click="reset"
                                class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            <i class="fas fa-times mr-2"></i> Cancel Import
                        </button>
                    </div>
                </div>
            </template>

            {{-- ============ PHASE 3: COMPLETED ============ --}}
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
        unlinkedMedicines: [],
        totalMissingMedicines: 0,
        totalUnlinkedMedicines: 0,
        affectedRows: [],
        stats: {},
        errors: [],

        allSuppliers: [],
        allCompanies: [],

        // Missing medicine form state — now holds both IDs AND typed names
        newMedicine: {
            company_id:    '',
            supplier_id:   '',
            company_name:  '',
            supplier_name: '',
        },

        // Unlinked medicine form state
        unlinkedSupplierId: '',

        // ---------- Upload ----------
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

        // ---------- Handle server response ----------
        async handleResponse(data) {
            if (data.status === 'paused') {
                this.phase = 'paused';
                this.batch = data.batch;
                this.pauseMessage = data.message;
                this.missingMedicines       = data.missing_medicines || [];
                this.unlinkedMedicines      = data.unlinked_medicines || [];
                this.totalMissingMedicines  = data.total_missing_medicines || 0;
                this.totalUnlinkedMedicines = data.total_unlinked_medicines || 0;
                this.affectedRows           = data.affected_rows || [];

                // Reset per-item form state on every pause
                this.newMedicine = {
                    company_id:    '',
                    supplier_id:   '',
                    company_name:  '',
                    supplier_name: '',
                };
                this.unlinkedSupplierId = '';

                await this.loadOptions();
            } else if (data.status === 'completed') {
                this.phase = 'completed';
                this.stats = data.stats || {};
            } else {
                this.errors = [data.message || 'Import failed'];
            }
        },

        // ---------- Load supplier + company options ----------
        async loadOptions() {
            try {
                const opts = await window.apiFetch('{{ route('sales.import.options') }}');
                this.allSuppliers = opts.suppliers || [];
                this.allCompanies = opts.companies || [];
            } catch (e) {
                console.error('Failed to load options', e);
            }
        },

        // ---------- Resolve ONE missing medicine: create or skip ----------
        async resolveMedicineAction(action) {
            if (!this.missingMedicines.length) return;

            const productCode = this.missingMedicines[0].product_code;

            if (action === 'create') {
                const hasCompany =
                    this.newMedicine.company_id ||
                    (this.newMedicine.company_name || '').trim() !== '';

                const hasSupplier =
                    this.newMedicine.supplier_id ||
                    (this.newMedicine.supplier_name || '').trim() !== '';

                if (!hasCompany) {
                    alert('Please pick or type a company.');
                    return;
                }
                if (!hasSupplier) {
                    alert('Please pick or type a supplier.');
                    return;
                }
            }

            this.loading = true;
            try {
                const url = '{{ url('/sales/import') }}/' + this.batch + '/resolve-medicine';
                const data = await window.apiFetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_code:      productCode,
                        action:            action,
                        company_id:        this.newMedicine.company_id    || null,
                        supplier_id:       this.newMedicine.supplier_id   || null,
                        new_company_name:  (this.newMedicine.company_name  || '').trim() || null,
                        new_supplier_name: (this.newMedicine.supplier_name || '').trim() || null,
                    }),
                });
                await this.handleResponse(data);
            } catch (e) {
                this.errors = [e.message];
            } finally {
                this.loading = false;
            }
        },

        // ---------- Resolve ONE unlinked medicine: link supplier ----------
        async resolveUnlinkedMedicine() {
            if (!this.unlinkedMedicines.length || !this.unlinkedSupplierId) {
                return;
            }

            const medicineId = this.unlinkedMedicines[0].medicine_id;
            const payload = {
                medicine_suppliers: {
                    [medicineId]: this.unlinkedSupplierId,
                },
            };

            this.loading = true;
            try {
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

        // ---------- Reset to fresh upload state ----------
        reset() {
            Object.assign(this, {
                phase: 'upload',
                batch: null,
                pauseMessage: '',
                missingMedicines: [],
                unlinkedMedicines: [],
                totalMissingMedicines: 0,
                totalUnlinkedMedicines: 0,
                affectedRows: [],
                stats: {},
                errors: [],
                allSuppliers: [],
                allCompanies: [],
                newMedicine: {
                    company_id:    '',
                    supplier_id:   '',
                    company_name:  '',
                    supplier_name: '',
                },
                unlinkedSupplierId: '',
            });
            if (this.$refs.file) this.$refs.file.value = '';
        },
    };
}
</script>
@endpush