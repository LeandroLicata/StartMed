@extends('layouts.app')

@section('titulo', 'Ingresar - StartMed')

@section('contenido')
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <h1 class="mb-6 text-center text-2xl font-semibold">StartMed</h1>

        <form method="POST" action="{{ route('login') }}"
              class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="nombreUsuario" class="block text-sm font-medium">Usuario</label>
                <input id="nombreUsuario" name="nombreUsuario" type="text" required autofocus
                       value="{{ old('nombreUsuario') }}"
                       class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Contrasena</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2">
            </div>

            @if ($errors->any())
                <ul class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <button type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 font-medium text-white hover:bg-gray-800">
                Ingresar
            </button>
        </form>
    </div>
</div>
@endsection
