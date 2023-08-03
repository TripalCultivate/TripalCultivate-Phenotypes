/**
 * @file
 * Manage events in stage accordion.
 */

(function($) {
  Drupal.behaviors.stageAccordion = {
    attach: function (context, settings) {
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
          // Inspect the current state of the content panel to know
          // if section should expanded or collapsed.
          if(this.classList.contains(tcpAccordion.active)) {
            // It is in collapsed state. Proceed to expand.
            this.classList.add(tcpAccordion.active);
          }
          else {
            // It is in expanded state. Proceed to collapse.
            this.classList.remove(tcpAccordion.active);
          }
        });
      }
    ///
}}}(jQuery));