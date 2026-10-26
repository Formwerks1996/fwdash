<div class="me-rate-legend flex flex-wrap items-center gap-2 text-xs text-muted">
    <span>{{ __('$/hr') }}</span>
    @foreach(\App\Support\RateBand::Bands as $band)
        <span class="rate-chip" style="background: {{ $band['wash'] }}; color: {{ $band['text'] }}; border-color: {{ $band['border'] }}">{{ $band['label'] }}</span>
    @endforeach
    <span class="rate-chip" style="background: {{ \App\Support\RateBand::Unmeasured['wash'] }}; color: {{ \App\Support\RateBand::Unmeasured['text'] }}; border-color: {{ \App\Support\RateBand::Unmeasured['border'] }}"><i class="ph ph-warning"></i>{{ __('Under :hours h logged', ['hours' => \App\Support\RateBand::MinHours]) }}</span>
</div>
