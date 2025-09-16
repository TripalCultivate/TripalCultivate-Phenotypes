/**
 * @file
 * Filter trait summary table by genus.
 */
(function($) {
  Drupal.behaviors.filterTraitTableByGenus = {
    attach: function (context, settings) {

      $('#tcp-filter-trait-table-by-genus').change(function() {
        var selectValue = $(this).val();

        var pathname = window.location.pathname;
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

      ///
}}}(jQuery));
