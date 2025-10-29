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

            try {
              $.ajax({
                url: Drupal.url(drupalSettings.tcpCombo['route']),
                method: 'POST',
                data: {
                  label: label,
                  combo: textField.data('combo'),
                  project: drupalSettings.tcpCombo['project'],
                  genus: drupalSettings.tcpCombo['genus'],
                  user: drupalSettings.tcpCombo['user'],
                },
                error: function(response) {
                  alert('Label is already used.');
                  textField.select();
                },
                success: function (response) {
                  var el = ($(btn).closest('tr').find('section').length > 1) ? 'section' : 'tr';
                  $(btn).closest(el).remove();
                }
              });
            } catch(e) {}
          });
        });

      ///
    }
  }
}(jQuery, Drupal, drupalSettings));
