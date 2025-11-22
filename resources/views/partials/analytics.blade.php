@if(config('analytics.ga_measurement_id'))
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('analytics.ga_measurement_id') }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ config('analytics.ga_measurement_id') }}');
</script>
@endif

{{-- Facebook Pixel removed: pixelization kept minimal on homepage only per request --}}
