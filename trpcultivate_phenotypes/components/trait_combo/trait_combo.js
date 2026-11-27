/**
 * @file
 * Trait combo behaviours.
 */

(function (Drupal, once) {

  Drupal.behaviors.traitCombo = {
    attach(context, settings) {

      // Reloads the parent window when a dialog containing combos is closed
      // via the X button of the dialog window.
      once('dialog-close', 'body', context).forEach(() => {
        document.addEventListener('dialog:afterclose', () => {
          var wl = window.location;
          wl.href = wl.pathname;
        });
      });

    }

  };
}) (Drupal, once);
