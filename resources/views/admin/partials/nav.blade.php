<nav class="segmented" aria-label="{{ __('Administración') }}">
    <a href="{{ route('admin.dashboard') }}" @class(['active' => request()->routeIs('admin.dashboard')])>{{ __('Resumen') }}</a>
    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>{{ __('Usuarios') }}</a>
    <a href="{{ route('admin.lists.index') }}" @class(['active' => request()->routeIs('admin.lists.*')])>{{ __('Listas públicas') }}</a>
</nav>
