(function ($) {

  "use strict";

  /* ===================================
     STELLAR (PARALLAX)
  =================================== */
  if ($.fn.stellar) {
    $(window).stellar({
      responsive: true,
      parallaxBackgrounds: true,
      parallaxElements: true,
      horizontalScrolling: false,
      hideDistantElements: false,
      scrollProperty: 'scroll'
    });
  }

  /* ===================================
     FULL HEIGHT SECTIONS
  =================================== */
  var fullHeight = function () {
    if ($('.js-fullheight').length > 0) {
      $('.js-fullheight').css('height', $(window).height());
      $(window).resize(function () {
        $('.js-fullheight').css('height', $(window).height());
      });
    }
  };
  fullHeight();

  /* ===================================
     LOADER
  =================================== */
  var loader = function () {
    setTimeout(function () {
      if ($('#ftco-loader').length > 0) {
        $('#ftco-loader').removeClass('show');
      }
    }, 1);
  };
  loader();

  /* ===================================
     CAROUSELS
  =================================== */

  // Home slider
  if ($('.home-slider').length > 0) {
    $('.home-slider').owlCarousel({
      loop: true,
      autoplay: true,
      margin: 0,
      animateOut: 'fadeOut',
      animateIn: 'fadeIn',
      nav: true,
      dots: true,
      items: 1,
      navText: [
        "<span class='ion-ios-arrow-back'></span>",
        "<span class='ion-ios-arrow-forward'></span>"
      ]
    });
  }

  // Testimony carousel
  if ($('.carousel-testimony').length > 0) {
    $('.carousel-testimony').owlCarousel({
      center: true,
      loop: true,
      items: 1,
      margin: 30,
      nav: false,
      responsive: {
        0: { items: 1 },
        600: { items: 2 },
        1000: { items: 3 }
      }
    });
  }

  // Stories carousel
  if ($('.carousel-stories').length > 0) {
    $('.carousel-stories').owlCarousel({
      loop: true,
      autoplay: true,
      margin: 30,
      nav: true,
      dots: false,
      items: 1,
      navText: [
        "<p><span class='fa fa-chevron-left'></span></p>",
        "<p><span class='fa fa-chevron-right'></span></p>"
      ]
    });
  }

  /* ===================================
     NAV DROPDOWN HOVER
  =================================== */
  $('nav .dropdown').hover(
    function () {
      $(this).addClass('show');
      $(this).find('> a').attr('aria-expanded', true);
      $(this).find('.dropdown-menu').addClass('show');
    },
    function () {
      $(this).removeClass('show');
      $(this).find('> a').attr('aria-expanded', false);
      $(this).find('.dropdown-menu').removeClass('show');
    }
  );

  /* ===================================
     MAGNIFIC POPUP
  =================================== */
  if ($('.image-popup').length > 0) {
    $('.image-popup').magnificPopup({
      type: 'image',
      closeOnContentClick: true,
      fixedContentPos: true,
      gallery: {
        enabled: true
      },
      zoom: {
        enabled: true,
        duration: 300
      }
    });
  }

  if ($('.popup-youtube, .popup-vimeo, .popup-gmaps').length > 0) {
    $('.popup-youtube, .popup-vimeo, .popup-gmaps').magnificPopup({
      disableOn: 700,
      type: 'iframe',
      mainClass: 'mfp-fade',
      removalDelay: 160,
      preloader: false,
      fixedContentPos: false
    });
  }

  /* ===================================
     COUNTER
  =================================== */
  if ($('#section-counter').length > 0 && $.fn.waypoint) {
    $('#section-counter').waypoint(function (direction) {
      if (direction === 'down') {
        $('.number').each(function () {
          var num = $(this).data('number');
          $(this).animateNumber(
            { number: num },
            7000
          );
        });
      }
    }, { offset: '95%' });
  }

  /* ===================================
     SCROLL ANIMATIONS
  =================================== */
  if ($('.ftco-animate').length > 0 && $.fn.waypoint) {
    $('.ftco-animate').waypoint(function (direction) {
      if (direction === 'down' && !$(this.element).hasClass('ftco-animated')) {
        $(this.element).addClass('fadeInUp ftco-animated');
      }
    }, { offset: '95%' });
  }

  /* ===================================
     DATE & TIME PICKERS
  =================================== */
  if ($('.appointment_date').length > 0) {
    $('.appointment_date').datepicker({
      format: 'm/d/yyyy',
      autoclose: true
    });
  }

  if ($('.appointment_time').length > 0) {
    $('.appointment_time').timepicker();
  }

})(jQuery);
