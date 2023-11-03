/**
 * @file
 * Auto-select project genus.
 */

(function($) {
  Drupal.behaviors.autoselectProjectGenus = {
    attach: function (context, settings) {
      $('#trpcultivate-fld-project', context).on('blur', function () {
        // Project - this is the project name.
        var project = $(this).val();
        
        if (project.length > 0) {
          // Route to get project genus.
          var route = '/admin/tripal/extension/tripal-cultivate/phenotypes/get-project-genus';
          
          $.ajax({
            url: route,
            method: 'POST',
            data: {
              value: project,
            },
            success: function (data) {
              // Genus field.
              var fldGenus = $('#trpcultivate-fld-genus');

              if (data) {
                // Set the genus field to the project genus.
                fldGenus.val(data);
              }
              else {
                // Reset genus.
                fldGenus.prop('selectedIndex', 0);
                // Inform user that the project entered has no genus set.
                alert('The project/experiment entered does not have a genus set.');
              }
            },
          });
        }
      });

      ///
}}}(jQuery));