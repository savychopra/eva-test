(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.mtGoogleMaps = {
    attach: function (context, settings) {

      var mapSelectorClass = "event-location-google-map-canvas";
      var mapSelector = "." + mapSelectorClass;

      function initialize() {
        $(context).find(mapSelector).once('mtGoogleMapsInit').each(function(index, item) {
          var map_locations_string = $(this).attr('data-attribute-mt-locations');
          var locations = JSON.parse(map_locations_string);
          var location = new google.maps.LatLng(locations[0][1], locations[0][2]);
          var zoom = parseInt($(this).attr('data-attribute-mt-map-zoom'));
          var baseMapURL = 'https://www.google.com/maps/dir/?api=1&destination=';

          var mapOptions = {
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            scrollwheel: false
          };

          var map = new google.maps.Map(this,mapOptions);
          var bounds = new google.maps.LatLngBounds();
          var infowindow = new google.maps.InfoWindow();
          var marker, i;

          for (i = 0; i < locations.length; i++) {  
            marker = new google.maps.Marker({
              position: new google.maps.LatLng(locations[i][1], locations[i][2]),
              map: map,
              draggable:false,
              url: baseMapURL + location
            });
            bounds.extend(marker.position);
            google.maps.event.addListener(marker, 'click', function() {
              window.open(this.url, "_blank");
            });
          }
          map.fitBounds(bounds);

          google.maps.event.addDomListener(window, "resize", function() {
            map.fitBounds(bounds);
          });

          if (zoom > 0) {
            var listener = google.maps.event.addListener(map, "idle", function () {
              map.setZoom(zoom);
              google.maps.event.removeListener(listener);
            });
          }

          $(".field--mt-collapsible-block .event-location-google-map-canvas", context).closest('.collapse').on('shown.bs.collapse', function() {
            google.maps.event.trigger(map, 'resize');
            map.fitBounds(bounds);
          });
        
          $( "#mt-get-directions-side-button" ).on( "click", function() {
            var _url = baseMapURL + location;
            window.open(_url, "_blank");
            return false;
          });
          
        });
      }
      google.maps.event.addDomListener(window, "load", initialize);

    }
  };
})(jQuery, Drupal, drupalSettings);
