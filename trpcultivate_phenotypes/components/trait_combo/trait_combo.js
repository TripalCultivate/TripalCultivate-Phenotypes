/**
 * @file
 * Trait combo behaviours.
 */

(function ($, Drupal) {
  Drupal.behaviors.traitCombo = {
    attach: function (context, settings) {

      // Reloads the parent window when a dialog containing combos is closed via
      // the X button of the dialog window.
      $(document).on('dialogclose', function() {
          var wl = window.location;
          wl.href = wl.pathname;
        }
      );

}}}(jQuery, Drupal));
