/**
 * @file
 * Trait combo behaviours.
 */

(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.traitCombo = {
    attach: function (context, settings) {

      var combo_class = '.tcp-trait-combo';

      // When a textfield is active, prevent the form from submitting
      // by pressing the enter key.
      $(combo_class + ' input[type="text"]')
        .on('keydown', function (event) {

          if (event.keyCode == 13) {
            event.preventDefault();
          }
        });

      // Add event listener to Add trait button.
      once('traitAssignCombo', '.tcp-add', context)
        .forEach(function (btn) {
          btn.addEventListener('click', function (event) {

            event.preventDefault();
            var textField = $(this).prev('input[type="text"]');
            var label = textField.val() || textField.data('default');

            $.ajax({
              url: Drupal.url(drupalSettings.tcpSettings['route']),
              method: 'POST',
              data: {
                label: label,
                combo: textField.data('combo'),
                project: drupalSettings.tcpSettings['project'],
                genus: drupalSettings.tcpSettings['genus'],
                user: drupalSettings.tcpSettings['user'],
              },
              complete: function () {

              }
            });
          });
        });

      ///
    }
  }
}(jQuery, Drupal, drupalSettings));
