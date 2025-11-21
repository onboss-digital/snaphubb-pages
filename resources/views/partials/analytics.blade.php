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

@php
    $fb_ids = config('analytics.fb_pixel_ids', []);
@endphp

@if(count($fb_ids))
<!-- Facebook Pixel (multiple) -->
<script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;
  // attach load/error handlers for diagnostics + simple retry
  t.onload = function(){
    try { console.info('FB Pixel: fbevents.js loaded'); window._fbq_loaded = true; } catch(e){}
  };
  t.onerror = function(){
    try { console.error('FB Pixel: failed to load fbevents.js, retrying with cache-bust'); window._fbq_loaded = false; } catch(e){}
    // retry once with cache-buster to avoid transient CDN/network issues
    try {
      var retry = b.createElement(e);
      retry.async = true;
      retry.src = v + '?_=' + Date.now();
      retry.onload = function(){ try{ console.info('FB Pixel: retry loaded fbevents.js'); window._fbq_loaded = true; }catch(e){} };
      retry.onerror = function(){ try{ console.error('FB Pixel: retry also failed to load fbevents.js'); window._fbq_loaded = false; }catch(e){} };
      s.parentNode.insertBefore(retry, s);
    } catch (err) {
      try { console.error('FB Pixel: retry failed to inject script', err); } catch(e){}
    }
  };
  s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');

  // Initialize all pixels
  @foreach($fb_ids as $id)
    fbq('init', '{{ $id }}');
  @endforeach

  // Grant consent (only if enabled in config)
  @if(config('analytics.fb_force_consent'))
    fbq('consent', 'grant');
  @endif

  // Send PageView to each pixel explicitly
  @foreach($fb_ids as $id)
    fbq('track', 'PageView', {}, {pixelId: '{{ $id }}'});
  @endforeach
</script>

<noscript>
  @foreach($fb_ids as $id)
    <img height="1" width="1" style="display:none"
      src="https://www.facebook.com/tr?id={{ $id }}&ev=PageView&noscript=1"/>
  @endforeach
</noscript>
@endif
