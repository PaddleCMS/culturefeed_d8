/**
 * @file
 * Provides google maps functionality.
 */

(function ($, Drupal, once) {

  Drupal.CultureFeed = Drupal.CultureFeed || {};
  Drupal.CultureFeed.Agenda = {};

  /**
   * Initialize the map.
   */
  Drupal.CultureFeed.Agenda.initializeMap = function (element) {
    var map = new google.maps.Map(element, {
      zoom: 12
    });

    var location = $(element).data('location');
    var latLong = new google.maps.LatLng(location.geo.latitude, location.geo.longitude);
    var marker = new google.maps.Marker({position: latLong, map: map});
    map.setCenter(latLong);

    var infowindow = new google.maps.InfoWindow({
      content: Drupal.CultureFeed.Agenda.mapInfoWindowContent(location, $(element).data('to_address')),
      maxWidth: 250
    });
    google.maps.event.addListener(marker, 'click', function () {
      infowindow.open(map, marker);
    });

  };

  /**
   * Create the info window content.
   */
  Drupal.CultureFeed.Agenda.mapInfoWindowContent = function (location, to_address) {
    var contentString = '<div>';
    if (location.name != null) {
      contentString += location.name + '<br />';
    }

    contentString += location.address.street + ' ';
    contentString += location.address.postalcode + ' ' + location.address.city + '<br />';

    contentString += '<br />' + Drupal.t('Directions') + ':' +
      '<form action="#" onsubmit="return Drupal.CultureFeed.Agenda.getDirections(this);"><input type="text" size="20" maxlength="38" name="saddr" value="';
    if (to_address) {
      contentString += to_address;
    }
    contentString += '" /> <input value="' + Drupal.t('Search') + '" type="submit">'
    contentString += '<input type="hidden" name="location" value=\'' + JSON.stringify(location) + '\'>';
    contentString += '</form>';
    contentString += '</div>';

    return contentString;
  };

  /**
   * Open the directions search on google maps.
   */
  Drupal.CultureFeed.Agenda.getDirections = function (form) {

    var $form = $(form);
    var saddr = $form.find("[name='saddr']");
    var location = JSON.parse($form.find("[name='location']").val());

    // Set the start and end locations.
    window.open('http://maps.google.be/maps?saddr=' + saddr.val() + '&daddr=' + escape(location.address.street + ', ' + location.address.postalcode + ' ' + location.address.city) + '&hl=en&z=15', '_blank');

    return false;
  };

  /**
   * Attach behaviors for displaying a google maps element.
   */
  Drupal.behaviors.culturfeed_agenda_google_maps = {
    attach: function attach(context) {
      $(once('search-autocomplete', '.google-map', context)).each(function (index, element) {
        Drupal.CultureFeed.Agenda.initializeMap(element);
      });
    }
  };

})(jQuery, Drupal, once);
