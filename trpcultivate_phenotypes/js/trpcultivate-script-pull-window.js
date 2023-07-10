/**
 * @file
 * Manage pull window (collapse/expand information window).
 */

(function($) {
  Drupal.behaviors.pullWindow = {
    attach: function (context, settings) {
      // Apply this functionality to container with class
      // tcp-pull-window.

      // Window control to anchor with class
      // tcp-toggle-pull-window.

      var fldClass = '.tcp-pull-window';
      var fldControl = '.tcp-toggle-pull-window';

      var pullWindow = $(fldClass);
      var buttonToggle = $(fldControl, context);
      
      buttonToggle.once(fldControl).click(function(e) {
        e.preventDefault();
        var linkControl = $(this);
        
        if (linkControl.text() == 'Need help?') {
          linkControl.text('Okay, got it!');
          $(fldClass).slideDown();
        }
        else {
          linkControl.text('Need help?');
          $(fldClass).slideUp();
        }
      });

      ///
}}}(jQuery));