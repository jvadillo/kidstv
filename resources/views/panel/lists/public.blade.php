@extends('layouts.panel')

@section('title', __('Listas públicas'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Listas públicas') }}</h1>
        <a href="{{ route('contents.index') }}" class="btn btn-ghost">{{ __('Volver a la biblioteca') }}</a>
    </div>
    <p class="muted">{{ __('Listas de vídeos preparadas por el equipo de KidsTV. Al añadir una, aparece en tu tele como una fila más y se mantiene al día sola: si se añaden o quitan vídeos, cambian también en tu videoteca.') }}</p>

    @if ($lists->isEmpty())
        <p class="empty">{{ __('Todavía no hay listas públicas.') }}</p>
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
@endsection
