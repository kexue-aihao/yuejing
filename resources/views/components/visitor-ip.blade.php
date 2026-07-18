@php($visitorIp = request()->ip())

@if($visitorIp)
    <aside class="visitor-ip" aria-label="{{ __('ui.components.current_ip') }}">
        <span class="visitor-ip-label">{{ __('ui.components.current_ip') }}</span>
        <code>{{ $visitorIp }}</code>
    </aside>
@endif
