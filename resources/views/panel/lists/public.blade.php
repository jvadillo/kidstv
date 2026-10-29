@extends('layouts.panel')

@section('title', __('Listas públicas'))

@php
    $url = fn (array $changes) => route('lists.public', array_filter(array_merge($filters, $changes)));
@endphp

@section('content')
    <a href="{{ route('contents.index') }}" class="back-link">@include('partials.icon', ['name' => 'left']) {{ __('Biblioteca') }}</a>

    <div class="page-head">
        <h1>{{ __('Listas públicas') }}</h1>
    </div>
    <p class="muted">{{ __('Listas de vídeos preparadas por el equipo de KidsTV. Al añadir una, aparece en tu tele como una fila más y se mantiene al día sola: si se añaden o quitan vídeos, cambian también en tu videoteca.') }}</p>

    @if ($total === 0)
        <p class="empty">{{ __('Todavía no hay listas públicas.') }}</p>
    @else
        <div class="library-filters">
            @if ($languages->count() > 1)
                @include('partials.filter-chips', [
                    'label' => __('Filtrar por idioma'),
                    'items' => collect([['url' => $url(['idioma' => null]), 'label' => __('Todos los idiomas'), 'current' => ! $filters['idioma']]])
                        ->concat($languages->map(fn ($name, $code) => ['url' => $url(['idioma' => $code]), 'label' => $name, 'current' => $filters['idioma'] === $code])->values()),
                ])
            @endif
            @include('partials.filter-chips', [
                'label' => __('Filtrar por edad recomendada'),
                'items' => collect([['url' => $url(['edad' => null]), 'label' => __('Todas las edades'), 'current' => ! $filters['edad']]])
                    ->concat(collect(\App\Models\VideoList::AGE_RANGES)->map(fn ($range, $key) => [
                        'url' => $url(['edad' => $key]), 'label' => \App\Models\VideoList::ageRangeLabel(...$range), 'current' => $filters['edad'] === $key,
                    ])->values()),
            ])
        </div>

        @if ($lists->isEmpty())
            <div class="empty-state">
                <p>{{ __('Ninguna lista coincide con estos filtros.') }}</p>
                <a href="{{ route('lists.public') }}" class="btn btn-ghost">{{ __('Quitar filtros') }}</a>
            </div>
        @else
            <ul class="card-list">
                @foreach ($lists as $list)
                    <li class="card public-list">
                        <a href="{{ route('lists.public.show', $list) }}" class="public-list-thumbs" aria-label="{{ __('Ver los vídeos de «:name»', ['name' => $list->name]) }}">
                            @forelse ($list->contents->take(4) as $content)
                                @include('partials.content-thumb', ['coverUrl' => $content->cover_path ? route('lists.public.cover', [$list, $content]) : null])
                            @empty
                                <span class="muted small">{{ __('Sin vídeos por ahora') }}</span>
                            @endforelse
                        </a>
                        <div class="public-list-body">
                            <h2>{{ $list->name }}</h2>
                            @if ($list->description)<p class="muted">{{ $list->description }}</p>@endif
                            @include('panel.lists.public-meta', ['list' => $list])
                            <p class="small muted">{{ trans_choice(':count vídeo|:count vídeos', $list->contents_count) }}</p>
                            @if (in_array($list->id, $linked, true))
                                <span class="btn btn-sm btn-added" aria-disabled="true">@include('partials.icon', ['name' => 'check']) {{ __('En tu videoteca') }}</span>
                            @else
                                <a href="{{ route('lists.public.show', $list) }}" class="btn btn-sm btn-primary">{{ __('Ver y añadir') }}</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
@endsection
