(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.mtOwlCarouselSponsors = {
    attach: function (context, settings) {
      $(context).find('.mt-carousel-sponsors').once('mtowlCarouselSponsorsInit').each(function() {
        $(this).owlCarousel({
          items: 2,
          responsive:{
            0:{
              items:2,
            },
            480:{
              items:2,
            },
            768:{
              items:3,
            },
            992:{
              items:4,
            },
            1200:{
              items:5,
            },
            1680:{
              items:5,
            }
          },
          autoplay: drupalSettings.conferenceplus.owlCarouselSponsorsInit.owlSponsorsAutoPlay,
          autoplayTimeout: drupalSettings.conferenceplus.owlCarouselSponsorsInit.owlSponsorsEffectTime,
          nav: true,
          dots: false,
          loop: true,
          navText: false
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
