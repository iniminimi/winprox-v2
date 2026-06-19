function readCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
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

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPromoVideoTracking);
} else {
  initPromoVideoTracking();
}
