/**
 * @file
 * Auto-select project genus.
 */

(function($) {
  Drupal.behaviors.autoselectProjectGenus = {
    attach: function (context, settings) {
      // Project field.
      var fldNameProject = '#trpcultivate-fld-project';
      var fldProject = $(fldNameProject);
      // Genus field.
      var fldGenus = $('#trpcultivate-fld-genus');
      
      // Add listener to project field when a project has been eneterd/selected.
      $(fldNameProject, context).on('blur', function () {
        // Project - this is the project name.
        var project = $(this).val();

        if (project.length > 0) {
          // Project entered.
          // Route to get project genus.
          var route = Drupal.url('admin/tripal/extension/tripal-cultivate/phenotypes/get-project-genus');
          
          $.ajax({
            url: route,
            method: 'POST',
            data: {
              project: project,
            },
            beforeSend: function() {
              // Disable project field while processing project-genus.
              fldProject.prop('disabled', true);
            },
            success: function (data) {
              if (data) {
                // Set the genus field to the project genus.
                fldGenus.val(data);
              }
              else {
                // Project has no genus, reset genus field.
                fldGenus.prop('selectedIndex', 0);
              }
            },
            complete: function(data) {
              // Re-enable project field
              fldProject.prop('disabled', false);
            }
          });
        }
        else {
          // No project, reset the genus field.
          fldGenus.prop('selectedIndex', 0);
        }
      });

      ///
}}}(jQuery));