/**
 * @file
 * Experiment configuration behaviours.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.traitSummaryPage = {
    attach: function (context, settings) {

      var pathname = window.location.pathname;
      var tcpSettings = drupalSettings.tcpSettings;

      if (tcpSettings && !pathname.includes('/' + tcpSettings.genus)) {
        // Update url to include the default single genus.
        window.history.pushState({}, '', pathname + '/' + tcpSettings.genus);
      }

      // Event listener to filter summary listing by genus select field.
      $('#tcp-filter-trait-table-by-genus')
        .change(function() {

          var selectValue = $(this).val();
          var pathPcs = pathname.split('/');
          var newLocation;

          if (pathPcs[pathPcs.length - 1] == 'configure') {
            newLocation = pathname + '/' + selectValue;
          }
          else {
            pathPcs[pathPcs.length - 1] = selectValue;

            if (selectValue == 0) {
              pathPcs.pop();
            }

            newLocation = window.location.origin + pathPcs.join('/');
          }

          window.location.href = newLocation;
      });

      // Add event listener to Add Trait button.
      $('#tcp-add-trait-to-experiment', context)
        .once('traitSummaryPage')
        .on('click', function(event) {

          // Stop the form from submitting.
          event.preventDefault();
      });

      ///
}}}(jQuery, Drupal, drupalSettings));
