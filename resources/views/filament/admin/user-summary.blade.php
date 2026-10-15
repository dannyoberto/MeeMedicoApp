{{-- Nombre y rol junto al avatar del menú de usuario, como en el mockup del backoffice. --}}
@php
    $user = filament()->auth()->user();
    $roles = $user?->getRoleNames()->map(fn (string $role) => \App\Models\Role::labelFor($role))->join(' · ');
@endphp

@if ($user)
    <div class="mm-user-summary">
        <span class="mm-user-summary-name">{{ $user->name }}</span>
        @if ($roles)
            <span class="mm-user-summary-role">{{ $roles }}</span>
        @endif
    </div>
@endif
