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
          tcpAccordionTitle[i].classList.add(tcpActive, 'tcp-current-stage');
          tcpAccordionTitle[i].nextElementSibling.style.display = 'block'
        }

        // Mark previous stages complete.
        if (i+1 < currentStage) {
          tcpAccordionTitle[i].classList.add('tcp-completed-stage');
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

      // Existing file.
      var elementFileExisting = $('select[name="file_upload_existing"]');
      elementFileExisting.prev().hide();
      elementFileExisting.next().hide();
      elementFileExisting.hide();

      // File server path.
      var elementFileServer = $('input[name="file_local"]');
      elementFileServer.prev().hide();
      elementFileServer.next().hide();
      elementFileServer.hide();
    }
  }
 } (jQuery, Drupal, drupalSettings)); 