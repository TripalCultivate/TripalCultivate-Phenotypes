/**
* @file
* Manage events in stage accordion.
*/


(function ($, Drupal, drupalSettings) {
  var initialized;
 
 
  /**
   * Initialize stage accordion.
   */
  function initAccordion() {
    if (!initialized) {
      initialized = true;
      ///
 
 
      // Stage accordion element class object.
      // @see css/style-accordion.
      var tcpAccordion = {};
      // Class title/header.
      tcpAccordion.title  = 'tcp-stage-accordion-title';
      tcpAccordion.active = 'tcp-stage-accordion-active';
      // Class accordion content panel collapsed.
      // Inactive content panel.
      tcpAccordion.off  = 'tcp-stage-accordion-content-off';
      // Class accordion content panel expanded.
      // Active content panel.
      tcpAccordion.on  = 'tcp-stage-accordion-content-on';
     
      // Reference all accordion title elements.
      var tcpAccordionTitle = document.getElementsByClassName(tcpAccordion.title);
      // Add click event listener.
      for(var i = 0; i < tcpAccordionTitle.length; i++) {
        tcpAccordionTitle[i].addEventListener('click', function() {
          // Reset any previously activated stage to only show
          // one stage at a time.
          var active = document.getElementsByClassName(tcpAccordion.active);
          var panels = document.getElementsByClassName(tcpAccordion.on);
          if (active.length > 0) {
            for (j = 0; j < active.length; j++) {
              active[j].classList.remove(tcpAccordion.active);
              panels[j].classList.replace(tcpAccordion.on, tcpAccordion.off);
            }
          }
 
 
          // Content panel of a stage.
          var tcpAccordionContent = this.nextElementSibling;
 
 
          // Inspect the current state of the stage title to know
          // if section should expanded or collapsed.
          if(!this.classList.contains(tcpAccordion.active)) {
            // It is in collapsed state. Proceed to expand.
            this.classList.add(tcpAccordion.active);
            tcpAccordionContent.classList.replace(tcpAccordion.off, tcpAccordion.on);
          }
          else {
            // It is in expanded state. Proceed to collapse.
            this.classList.remove(tcpAccordion.active);
            tcpAccordionContent.classList.replace(tcpAccordion.on, tcpAccordion.off);
          }
        });
      }
    }
  }
   Drupal.behaviors.stageAccordion = {
    attach: function(context, settings) {
      initAccordion();
    }
  }
 } (jQuery, Drupal, drupalSettings)); 