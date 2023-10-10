/**
* @file
* Manage events in stage accordion.
*/

(function ($, Drupal, drupalSettings) {
  var initialized;
 
   /**
   * Initialize stage accordion.
   * 
   * @param int currentStage
   *   Current stage number from the Importer plugin #attach settings element.
   */
  function initAccordion(currentStage) {
    if (!initialized) {
      initialized = true;
      ///
      var tcpActive = 'tcp-stage-active';

      // Reference all stage accordion title elements.
      var tcpAccordionTitle = document.querySelectorAll('.tcp-stage-title');
      // Add click event listener.
      for(var i = 0; i < tcpAccordionTitle.length; i++) {
        // Add active class to the current stage.
        if (i+1 == currentStage) {
          tcpAccordionTitle[i].classList.add(tcpActive);
        }
          
        tcpAccordionTitle[i].addEventListener('click', function() {
          // Reset any previously activated stage to only show
          // one stage at a time.
          var active = document.querySelectorAll('.' + tcpActive);
          if (active.length > 0) {
            for (j = 0; j < active.length; j++) {
              active[j].classList.remove(tcpActive);
              active[j].nextElementSibling.style.display = 'none';
            }
          }
          
          // Content panel of a stage.
          var tcpAccordionContent = this.nextElementSibling;
          
          // When clicked, set the title to active stage
          // and expand the content area.
          this.classList.add(tcpActive);
          tcpAccordionContent.style.display = 'block';
        });
      }
    }
  }
  
  Drupal.behaviors.stageAccordion = {
    attach: function(context, settings) {
      var currentStage = $('#tcp-current-stage').val();
      initAccordion(currentStage);
    }
  }
 } (jQuery, Drupal, drupalSettings)); 