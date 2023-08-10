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
      var tcpAccordionTitle = document.querySelectorAll('.tcp-stage');
      // Add click event listener.
      for(var i = 0; i < tcpAccordionTitle.length; i++) {
        // Activate current stage.
        if (i+1 == currentStage) {
          tcpAccordionTitle[i].classList.add('tcp-current-stage');
          tcpAccordionTitle[i].nextElementSibling.style.display = 'block'
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
           
          // Inspect the current state of the stage title to know
          // if section should expanded or collapsed.
          if(!this.classList.contains(tcpActive)) {
            // It is in collapsed state. Proceed to expand.
            this.classList.add(tcpActive);
            tcpAccordionContent.style.display = 'block'
          }
          else {
            // It is in expanded state. Proceed to collapse.
            this.classList.remove(tcpActive);
            tcpAccordionContent.style.display = 'none';
          }

        });
      }
    }
  }
  
  Drupal.behaviors.stageAccordion = {
    attach: function(context, settings) {
      var currentStage = drupalSettings.trpcultivate_phenoshare.current_stage;
      initAccordion(currentStage);
    }
  }
 } (jQuery, Drupal, drupalSettings)); 