function readCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function initPromoEngagement() {
    const trackUrl = document.body.dataset.promoEngageUrl;
    if (!trackUrl) {
        return;
    }

    let sent = false;

    function sendEngage() {
        if (sent) {
            return;
        }

        sent = true;

        window.fetch(trackUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ page: /\/promo(?:\/|$|\?)/.test(window.location.pathname) ? 'promo' : 'welcome' }),
        }).catch(() => {
            // Promo UX must continue when analytics fails.
        });
    }

    window.setTimeout(sendEngage, 8000);

    window.addEventListener(
        'scroll',
        function onScroll() {
            if (window.scrollY < 160) {
                return;
            }

            window.removeEventListener('scroll', onScroll);
            sendEngage();
        },
        { passive: true },
    );
}

function initPromoVideoTracking() {
  if (!document.body.dataset.promoTracking) {
    return;
  }

  const trackUrl = document.body.dataset.promoTrackUrl;
  if (!trackUrl) {
    return;
  }

  document.addEventListener(
    'play',
    (event) => {
      const target = event.target;
      if (!(target instanceof HTMLVideoElement)) {
        return;
      }

      const wrapper = target.closest('[data-promo-video-key]');
      if (!wrapper) {
        return;
      }

      const videoKey = wrapper.getAttribute('data-promo-video-key');
      if (!videoKey) {
        return;
      }

      window.fetch(trackUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': readCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ video_key: videoKey }),
      }).catch(() => {
        // Promo UX must continue when analytics fails.
      });
    },
    true,
  );
}

function initPromoTracking() {
    initPromoVideoTracking();
    initPromoEngagement();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPromoTracking);
} else {
  initPromoTracking();
}
