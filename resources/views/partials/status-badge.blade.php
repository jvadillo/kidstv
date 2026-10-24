<span class="badge badge-{{ $content->status }}">{{ $content->isHidden() ? __('Oculto') : $content->statusLabel() }}</span>
