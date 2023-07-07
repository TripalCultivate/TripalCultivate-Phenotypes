/**
 * @file
 * Auto-select field content form quick alteration of values.
 */
(function($) {
  Drupal.behaviors.autoselectField = {
    attach: function (context, settings) {
      // Apply this functionality when field class is set
      // to tcp-autocomplete.
      var fldClass = '.tcp-autocomplete';
      
      $(fldClass).click(function(e) {
        var fld = $(this);

        if (fld.val()) {
          fld.select();
        }
      });
      
      ///
}}}(jQuery));