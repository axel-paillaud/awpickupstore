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

  /** Remove any previously injected awpickupstore content. */
  function clearAwContent() {
    document.querySelectorAll('.awpickupstore-extra').forEach(function (el) {
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
        + '<div class="row g-2 mx-0">'
        + '<div class="col-auto">'
        + '<input type="date" name="awpickupstore_date" class="form-control"'
        + ' min="' + cfg.min_date + '" required'
        + ' aria-label="' + i18n.date_label + '">'
        + '</div>'
        + '<div class="col-auto mt-1">'
        + '<input type="time" name="awpickupstore_time" class="form-control"'
        + ' required aria-label="' + i18n.time_label + '">'
        + '</div>'
        + '</div>'
        + '</div>';
    }

    html += '</div>';
    return html;
  }

  // Track the last injected carrier to avoid re-injecting on unrelated form changes
  // (PS fires updatedDeliveryForm on any input change in #js-delivery, including our date picker)
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
