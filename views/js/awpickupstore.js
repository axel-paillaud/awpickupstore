/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

/* awpickupstore — Front Office
 *
 * Carrier config (message + appointment) is embedded by hookDisplayBeforeCarrier
 * as a JSON blob in #awpickupstore-config.
 *
 * On each carrier selection, JS injects the relevant HTML into the carrier's
 * .carrier-extra-content div (which is inside #js-delivery form, so inputs submit).
 *
 * Triggers:
 * - DOMContentLoaded: initial selected carrier
 * - change on delivery_option radios: immediate feedback
 * - prestashop.on('updatedDeliveryForm'): after PS AJAX confirms the selection
 */

(function () {
  'use strict';

  var configEl = document.getElementById('awpickupstore-config');
  if (!configEl) return;

  var config;
  try {
    config = JSON.parse(configEl.textContent || configEl.innerHTML);
  } catch (e) {
    return;
  }
  if (!config || !config.carriers) return;

  /**
   * Get the currently selected carrier ID from the delivery_option radio.
   * PS radio values are formatted as "{id_carrier}," (trailing comma).
   */
  function getSelectedCarrierId() {
    var radio = document.querySelector('input[name^="delivery_option"]:checked');
    return radio ? parseInt(radio.value, 10) : 0;
  }

  /**
   * Find the .carrier-extra-content div associated with a given carrier.
   * Structure: .delivery-option (contains radio) + .carrier-extra-content (next sibling).
   */
  function findExtraContent(carrierId) {
    var radio = document.getElementById('delivery_option_' + carrierId);
    if (!radio) return null;
    var deliveryOption = radio.closest('.delivery-option');
    if (!deliveryOption) return null;
    var el = deliveryOption.nextElementSibling;
    while (el) {
      if (el.classList.contains('carrier-extra-content')) return el;
      if (el.classList.contains('delivery-option')) break;
      el = el.nextElementSibling;
    }
    return null;
  }

  /** Remove any previously injected awpickupstore content (destroy Flatpickr first). */
  function clearAwContent() {
    document.querySelectorAll('.awpickupstore-extra').forEach(function (el) {
      var fpInput = el.querySelector('.awpickupstore-datetime-picker');
      if (fpInput && fpInput._flatpickr) {
        fpInput._flatpickr.destroy();
      }
      el.remove();
    });
  }

  function buildHtml(cfg) {
    var i18n = config.i18n;
    var html = '<div class="awpickupstore-extra">';

    if (cfg.message) {
      html += '<div class="awpickupstore-message alert alert-info mt-2 mb-2">' + cfg.message + '</div>';
    }

    if (cfg.require_appointment) {
      html += '<div class="awpickupstore-appointment mt-2">'
        + '<p class="awpickupstore-appointment__label mb-2">'
        + '<i class="material-icons awpickupstore-icon">event</i> '
        + i18n.appointment_label
        + '</p>'
        + '<input type="text" class="form-control awpickupstore-datetime-picker" placeholder="' + i18n.date_placeholder + '" readonly>'
        + '<input type="hidden" name="awpickupstore_datetime">'
        + '</div>';
    }

    html += '</div>';
    return html;
  }

  /** Initialise Flatpickr on the newly injected datetime input. */
  function initFlatpickr(container, cfg) {
    var pickerInput = container.querySelector('.awpickupstore-datetime-picker');
    var hiddenInput = container.querySelector('input[name="awpickupstore_datetime"]');
    if (!pickerInput || !hiddenInput || !window.flatpickr) return;

    var fpOptions = {
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      minDate: cfg.min_date,
      time_24hr: true,
      onChange: function (selectedDates, dateStr) {
        hiddenInput.value = dateStr;
      },
    };

    // Apply French locale if available
    if (config.locale_iso === 'fr' && flatpickr.l10ns && flatpickr.l10ns.fr) {
      fpOptions.locale = flatpickr.l10ns.fr;
    }

    flatpickr(pickerInput, fpOptions);
  }

  // Track the last injected carrier to avoid re-injecting on unrelated form changes
  // (PS fires updatedDeliveryForm on any input change in #js-delivery, including our picker)
  var activeCarrierId = 0;

  function update() {
    var id = getSelectedCarrierId();
    if (id === activeCarrierId) return;
    activeCarrierId = id;

    clearAwContent();
    var cfg = id && config.carriers[id];
    if (!cfg) return;
    var container = findExtraContent(id);
    if (!container) return;

    container.insertAdjacentHTML('beforeend', buildHtml(cfg));

    if (cfg.require_appointment) {
      initFlatpickr(container, cfg);
    }
  }

  // Initial render on page load
  update();

  // Immediate feedback on radio change (before PS AJAX)
  document.addEventListener('change', function (e) {
    if (e.target && e.target.name && e.target.name.indexOf('delivery_option') !== -1) {
      update();
    }
  });

  // Re-inject after PS AJAX has hidden/shown the correct .carrier-extra-content
  if (window.prestashop) {
    prestashop.on('updatedDeliveryForm', function () {
      update();
    });
  }
}());
