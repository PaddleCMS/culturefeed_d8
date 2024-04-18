/**
 * @file
 * Provides hidden value autocompletion.
 */

(function ($, Drupal, once) {
  Drupal.hiddenValueAutocomplete = {};

  /**
   * Attach behaviors to the hidden value autocomplete element.
   */
  Drupal.behaviors.hiddenValueAutocomplete = {
    attach: function attach(context) {
      var $autocomplete = $(once('hidden-value-autocomplete', context)).find('.hidden-value-autocomplete');
      if ($autocomplete.length) {
        // Select handler.
        $autocomplete.autocomplete('option', 'select', Drupal.hiddenValueAutocomplete.autocompleteSelectHandler);
        // Source handler.
        $autocomplete.autocomplete('option', 'source', Drupal.hiddenValueAutocomplete.autocompleteSourceHandler);
        // Search handler.
        $autocomplete.autocomplete('option', 'search', Drupal.hiddenValueAutocomplete.autocompleteSearchHandler);
        // Minimum length.
        $autocomplete.autocomplete('option', 'minLength', 3);
        // Clear the hidden field when the autocomplete field changes.
        $autocomplete.on('keyup', function (event) {
          $(event.target).closest('.hidden-value-autocomplete--group').find('input[type="hidden"]').val('');
        });
      }
    }
  };

  /**
   * Custom select handler to fill in the hidden field value.
   */
  Drupal.hiddenValueAutocomplete.autocompleteSelectHandler = function (event, ui) {
    event.target.value = ui.item.label;
    $(event.target).closest('.hidden-value-autocomplete--group').find('input[type="hidden"]').val(ui.item.value);
    // Trigger select event.
    $(event.target).trigger('autocomplete-select');
    return false;
  };

  /**
   * Custom search handler to fill in the hidden field value.
   */
  Drupal.hiddenValueAutocomplete.autocompleteSearchHandler = function (event, ui) {
    var options = Drupal.autocomplete.options;
    if (options.isComposing) {
      return false;
    }
    var term = event.target.value;
    if (term.length > 0 && options.firstCharacterBlacklist.indexOf(term) !== -1) {
      return false;
    }
    return term.length >= options.minLength;
  };

  /**
   * Custom source handler to fill in the hidden field value.
   */
  Drupal.hiddenValueAutocomplete.autocompleteSourceHandler = function (request, response) {
    var elementId = this.element.attr('id');
    if (!(elementId in Drupal.autocomplete.cache)) {
      Drupal.autocomplete.cache[elementId] = {};
    }

    function showSuggestions(suggestions) {
      var tagged = request.term;
      var il = tagged.length;
      for (var i = 0; i < il; i++) {
        var index = suggestions.indexOf(tagged[i]);
        if (index >= 0) {
          suggestions.splice(index, 1);
        }
      }
      response(suggestions);
    }

    function sourceCallbackHandler(data) {
      Drupal.autocomplete.cache[elementId][term] = data;
      showSuggestions(data);
    }

    var term = request.term;
    if (Drupal.autocomplete.cache[elementId].hasOwnProperty(term)) {
      showSuggestions(Drupal.autocomplete.cache[elementId][term]);
    }
    else {
      var options = $.extend({
        success: sourceCallbackHandler,
        data: {q: term}
      }, Drupal.autocomplete.ajax);
      $.ajax(this.element.attr('data-autocomplete-path'), options);
    }
  };

})(jQuery, Drupal, once);
