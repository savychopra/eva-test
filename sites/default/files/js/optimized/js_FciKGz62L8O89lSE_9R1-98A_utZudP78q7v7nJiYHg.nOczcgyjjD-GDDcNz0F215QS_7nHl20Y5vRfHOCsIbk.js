(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.mtTypedjs = {
    attach: function (context, settings) {
      $(context).find('.typed').once('mtTypedjsInit').each(function() {
        var typed = new Typed(this, {
          stringsElement: '.typed-strings',
          loop: true,
          typeSpeed: 40,
          backSpeed: 30,
          backDelay: 4000
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
