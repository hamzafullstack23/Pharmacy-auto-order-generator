<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Pharmacy Management System')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('styles')
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">

    <!-- Navbar -->
    <nav class="w-full bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">

                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-xl font-semibold text-gray-800">
                            Pharmacy Manager
                        </span>
                    </div>

                    @auth
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="{{ route('dashboard') }}"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>

                        <a href="{{ route('suppliers.index') }}"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Suppliers
                        </a>

                        <a href="{{ route('medicines.index') }}"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Medicines
                        </a>

                        <a href="{{ route('orders.index') }}"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Orders
                        </a>

                        <a href="{{ route('sales.upload') }}"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Sales Import
                        </a>
                    </div>
                    @endauth
                </div>

                <div class="flex items-center">
                    @auth
                    <span class="text-sm text-gray-700 mr-4">
                        {{ auth()->user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Logout
                        </button>
                    </form>
                    @endauth
                </div>

            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <main class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            {{ session('warning') }}
        </div>
        @endif

        @if(session('info'))
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
            {{ session('info') }}
        </div>
        @endif

        @yield('content')

    </main>
    <script>
        window.apiFetch = async function(url, options = {}) {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const headers = Object.assign({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            }, options.headers || {});

            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin'
            });
            const text = await response.text();

            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML (status ' + response.status + '). Check logs.');
            }

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON: ' + text.slice(0, 200));
            }

            if (!response.ok) {
                const err = new Error(data.message || ('HTTP ' + response.status));
                err.response = data;
                err.status = response.status;
                throw err;
            }
            return data;
        };
    </script>
    @stack('scripts')

</body>

</html>