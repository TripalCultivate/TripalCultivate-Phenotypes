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
        .on('click', function(event) {

          // Stop the form from submitting.
          event.preventDefault();
      });

      // Add event listener to input fields and stop the form from submitting
      // after providing value then hitting the enter key.
      $('#drupal-modal input')
        .on('keydown', function (event) {

          if (event.keyCode == 13) {
            event.preventDefault();
          }
      });

      // Add event listener to click event where user selects an option in
      // the suggestions.
      var class_suggestions = '.ui-autocomplete';
      if ($(class_suggestions)) {
        $(document).on('click', class_suggestions, function() {

          $('#tcp-search-trait-field')
            .val($(this).text())
            .trigger('change');
        });
      }

      // Drupal got the stacking order of the popup window and overlay incorrect.
      // This will override the set values and other styling.
      $(document, context)
        .on('dialogopen', function(event) {

          var element = $(event.target).closest('.ui-dialog');

          element
            .css({'z-index': 102});

          element
            .find('.ui-dialog-content')
            .css({'padding-top': '20px'});

          element
            .next('.ui-widget-overlay')
            .css({'z-index': 101});
      });

      ///
}}}(jQuery, Drupal, drupalSettings));
