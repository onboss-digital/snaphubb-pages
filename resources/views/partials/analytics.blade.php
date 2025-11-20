@if(env('GA_MEASUREMENT_ID'))
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ env('GA_MEASUREMENT_ID') }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ env('GA_MEASUREMENT_ID') }}');
</script>
@endif

@php
    $fb_ids = [];
    if(env('FB_PIXEL_IDS')){
        $fb_ids = array_filter(array_map('trim', explode(',', env('FB_PIXEL_IDS'))));
    } elseif(env('FB_PIXEL_ID')){
        $fb_ids = [env('FB_PIXEL_ID')];
    }
@endphp

@if(count($fb_ids))
<!-- Facebook Pixel (multiple) -->
<script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');

  // Initialize all pixels
  @foreach($fb_ids as $id)
    fbq('init', '{{ $id }}');
  @endforeach

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
