@php
    $bisnis = app(\App\Services\BusinessInfoService::class);
    $namaUsaha = $bisnis->namaUsaha();
    $logoUrl = $bisnis->logoUrl();
@endphp
<div class="flex min-h-screen flex-col items-center justify-center px-6">
    <div class="mb-8 flex flex-col items-center">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="Logo {{ $namaUsaha }}"
                class="mb-4 h-16 w-16 rounded-2xl bg-white object-contain p-1 shadow-lg shadow-blue-200 ring-1 ring-gray-100">
        @else
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 shadow-lg shadow-blue-200">
                <x-heroicon-o-building-office-2 class="h-8 w-8 text-white" />
            </div>
        @endif
        <h1 class="text-xl font-bold text-gray-900">{{ $namaUsaha }}</h1>
        <p class="text-sm text-gray-500">Portal Customer</p>
    </div>

    <div class="w-full rounded-2xl bg-white p-6 shadow-sm">
        <form wire:submit="login" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input type="email" wire:model="email" class="w-full rounded-xl border border-gray-300 px-4 py-2.5" autofocus>
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Kata Sandi</label>
                <input type="password" wire:model="password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-full bg-blue-600 py-3 font-semibold text-white shadow-md shadow-blue-200 active:bg-blue-700">
                Masuk
            </button>
        </form>
        <p class="mt-4 text-center text-xs text-gray-400">Belum punya akses Portal? Hubungi admin {{ $namaUsaha }}.</p>
    </div>
</div>
