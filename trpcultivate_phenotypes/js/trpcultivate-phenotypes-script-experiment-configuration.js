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


      var search_field = $('#tcp-trait');

      // Add event listener to anchor tag to view all available traits.
      $('#tcp-result-wrapper a')
        .on('click', function(event) {

          event.preventDefault();
          search_field.trigger('change');
        })

      $(document)
        .ready(function() {

          // Cursor on the search field on page load.
          search_field.focus();
        })
        .on('dialogopen', function(event) {

          // Adjust the stacking order or the window and overlay.
          var element = $(event.target).closest('.ui-dialog');

          element
            .css({'z-index': 102});

          element
            .find('.ui-dialog-content')
            .css({'padding-top': '20px'});

          element
            .next('.ui-widget-overlay')
            .css({'z-index': 101});
        })
        .on('click', '.ui-autocomplete li', function () {

          // Add event listener to load result when clicking a suggestion.
          search_field
            .val($(this).text())
            .trigger('change');
        });

      ///
}}}(jQuery, Drupal, drupalSettings));
