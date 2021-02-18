(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.mtMagnificPopupFieldAlbImage = {
    attach: function (context, settings) {
      $(context).find('.field--name-field-mt-alb-image a.image-popup').once('mtMagnificPopupFieldAlbImageInit').magnificPopup({
        type:"image",
        removalDelay: 300,
        mainClass: "mfp-fade",
        gallery: {
          enabled: true, // set to true to enable gallery
        },
        image: {
          titleSrc: function(item) {
            return item.el.closest('.overlay-container').children()[1].title || '';
          }
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
