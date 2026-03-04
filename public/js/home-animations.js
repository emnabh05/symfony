(function () {
  'use strict';

  function applyReveal(selector, options) {
    var settings = options || {};
    var effect = settings.effect || 'up';
    var step = typeof settings.stagger === 'number' ? settings.stagger : 80;
    var baseDelay = typeof settings.baseDelay === 'number' ? settings.baseDelay : 0;
    var nodes = document.querySelectorAll(selector);

    nodes.forEach(function (node, index) {
      node.setAttribute('data-reveal', effect);
      node.style.setProperty('--reveal-delay', (baseDelay + (index * step)) + 'ms');
    });
  }

  function markRevealTargets() {
    applyReveal('.main-hero .hero-kicker, .main-hero .hero-headline, .main-hero .hero-subheadline, .main-hero .hero-ctas', { effect: 'up', stagger: 90, baseDelay: 80 });

    applyReveal('.ftco-services .services-wrap', { effect: 'up', stagger: 110 });

    applyReveal('.ftco-section.bg-light .row.no-gutters .col-md-5.img', { effect: 'left' });
    applyReveal('.ftco-section.bg-light .row.no-gutters .wrap-about', { effect: 'right', baseDelay: 70 });

    applyReveal('.ftco-no-pt.ftco-no-pb .consultation', { effect: 'up', stagger: 90 });
    applyReveal('.ftco-no-pt.ftco-no-pb .appointment-form .form-group', { effect: 'up', stagger: 55, baseDelay: 110 });

    applyReveal('.testimony-section .heading-section', { effect: 'up' });
    applyReveal('.testimony-section .testimony-wrap', { effect: 'up', stagger: 90, baseDelay: 80 });

    applyReveal('.ftco-section .icon', { effect: 'scale', stagger: 70 });

    applyReveal('.carousel-stories .stories-wrap .img', { effect: 'left' });
    applyReveal('.carousel-stories .stories-wrap .desc > *', { effect: 'up', stagger: 70, baseDelay: 70 });

    applyReveal('.block-7', { effect: 'up', stagger: 90 });

    applyReveal('.blog-entry', { effect: 'up', stagger: 95 });

    applyReveal('.ftco-intro .row > div', { effect: 'up', stagger: 90 });
  }

  function initObserver() {
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var targets = document.querySelectorAll('[data-reveal]');

    if (reduceMotion || !('IntersectionObserver' in window)) {
      targets.forEach(function (target) {
        target.classList.add('is-visible');
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add('is-visible');
        obs.unobserve(entry.target);
      });
    }, {
      threshold: 0.18,
      rootMargin: '0px 0px -10% 0px'
    });

    targets.forEach(function (target) {
      observer.observe(target);
    });
  }

  function init() {
    document.body.classList.add('home-motion-enabled');
    markRevealTargets();
    initObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();