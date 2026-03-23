<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Electrician Dashboard') - {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="min-h-screen">
            <nav class="bg-white border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center space-x-4">
                            <a href="{{ route('electrician.dashboard') }}"
                                class="{{ request()->routeIs('electrician.dashboard') ? 'text-amber-700' : 'text-gray-700 hover:text-gray-900' }} text-sm font-medium">
                                Dashboard
                            </a>
                            <a href="{{ route('electrician.rewards.index') }}"
                                class="{{ request()->routeIs('electrician.rewards.*') ? 'text-amber-700' : 'text-gray-700 hover:text-gray-900' }} text-sm font-medium">
                                Reward Points
                            </a>
                            <a href="{{ route('electrician.orders.create') }}"
                                class="{{ request()->routeIs('electrician.orders.*') ? 'text-amber-700' : 'text-gray-700 hover:text-gray-900' }} text-sm font-medium">
                                {{ __('Create order') }}
                            </a>
                        </div>

                        <div class="flex items-center gap-2 sm:gap-3">
                            <a href="{{ route('home') }}" class="inline-flex items-center rounded-md px-2 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-50">
                                Shop
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="flex">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-md px-2 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-50">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="py-8">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    @if (session('success'))
                        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
        @stack('scripts')
    </body>
</html>

