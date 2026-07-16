@php($visitorIp = request()->ip())

@if($visitorIp)
    <aside class="visitor-ip" aria-label="当前访问 IP">
        <span class="visitor-ip-label">当前访问 IP</span>
        <code>{{ $visitorIp }}</code>
    </aside>
@endif
