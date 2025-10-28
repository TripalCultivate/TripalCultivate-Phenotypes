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

      // Trait Picker:
      var genusField = $('#tcp-genus');
      var traitField = $('#tcp-trait');
      var resultWrapper = $('#tcp-result-wrapper');
      var clearClass = 'tcp-autocomplete-clear';

      traitField
        .on('keydown', function(event) {

          if (event.keyCode == 13) {
            event.preventDefault();
            $(this)
              .addClass(clearClass)
              .trigger('change');
          }
        })
        .on('click', function(event) {

          if ($(this).hasClass(clearClass)) {
            $(this)
              .val('')
              .removeClass(clearClass);

            resultWrapper.empty();
          }
        });

      resultWrapper
        .find('a')
        .on('click', function(event) {

          event.preventDefault();

          traitField
            .val('All')
            .addClass(clearClass)
            .trigger('change');
        });

      $(document)
        .ready(function () {

          // Cursor on the trait/genus field on page load.
          var el = genusField.val() == 0 ? genusField : traitField;
          el.focus();
        })
        .on('dialogopen', function (event, ui) {

          // Adjust the stacking order or the window and overlay.
          var dialog = $(event.target).closest('.ui-dialog');

          dialog
            .css({ 'z-index': 102 });

          dialog
            .find('.ui-dialog-content')
            .css({ 'padding-top': '20px' });

          dialog
            .next('.ui-widget-overlay')
            .css({ 'z-index': 101 });
        })
        .on('click', '.ui-autocomplete li', function () {

          // Add event listener to load result when clicking a suggestion.
          traitField
            .val($(this).text())
            .addClass(clearClass)
            .trigger('change');
        });

      ///
}}}(jQuery, Drupal, drupalSettings));
