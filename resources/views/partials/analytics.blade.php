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
  // Ensure we only attempt initialization once per page load to avoid loops
  if (!window._fbq_init_attempted) {
    window._fbq_init_attempted = true;

    (function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;
    // attach load/error handlers for diagnostics + single retry
    t.onload = function(){
      try { window._fbq_loaded = true; } catch(e){}
    };
    t.onerror = function(){
      try { window._fbq_loaded = false; } catch(e){}
      // retry once with cache-buster to avoid transient CDN/network issues
      try {
        var retry = b.createElement(e);
        retry.async = true;
        retry.src = v + '?_=' + Date.now();
        retry.onload = function(){ try{ window._fbq_loaded = true; }catch(e){} };
        retry.onerror = function(){ try{ window._fbq_loaded = false; }catch(e){} };
        s.parentNode.insertBefore(retry, s);
      } catch (err) {
        // swallow errors silently in production
      }
    };
    s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');

    // Initialize all pixels (fbq stub will queue init if script not loaded yet)
    @foreach($fb_ids as $id)
      try{ fbq('init', '{{ $id }}'); } catch(e){}
    @endforeach

    // Grant consent (only if enabled in config) — do not force by default
    @if(config('analytics.fb_force_consent'))
      try{ fbq('consent', 'grant'); } catch(e){}
    @endif

    // Send PageView to each pixel explicitly, but avoid noisy logs
    @foreach($fb_ids as $id)
      (function(pixelId){
        function sendPageView(){
          try {
            if (typeof fbq === 'function' && typeof fbq.track === 'function'){
              fbq('track', 'PageView', {}, {pixelId: pixelId});
              return true;
            }
          } catch(e) { /* silent */ }
          return false;
        }

        // Try immediate send (will queue if fbq stub supports it)
        if (!sendPageView()){
          // Wait for load flag set by script load handler (we set window._fbq_loaded)
          var tries = 0;
          var iv = setInterval(function(){
            tries++;
            if (window._fbq_loaded === true || (typeof fbq === 'function' && typeof fbq.track === 'function')){
              sendPageView();
              clearInterval(iv);
            } else if (tries > 6) {
              // Last resort: attempt to call fbq anyway (stub will queue). No noisy logs.
              try { fbq('track', 'PageView', {}, {pixelId: pixelId}); } catch(e){ /* silent fail */ }
              clearInterval(iv);
            }
          }, 400);
        }
      })('{{ $id }}');
    @endforeach
  }
</script>

<noscript>
  @foreach($fb_ids as $id)
    <img height="1" width="1" style="display:none"
      src="https://www.facebook.com/tr?id={{ $id }}&ev=PageView&noscript=1"/>
  @endforeach
</noscript>
@endif
