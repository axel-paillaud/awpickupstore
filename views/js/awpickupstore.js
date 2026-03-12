/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

/* awpickupstore — Front Office
 *
 * Carrier config (message + appointment + store picker) is embedded by hookDisplayBeforeCarrier
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

  /** Build the store index keyed by id for fast lookup in the select change handler. */
  function buildStoreIndex(stores) {
    var index = {};
    if (!stores) return index;
    stores.forEach(function (s) {
      index[s.id] = s;
    });
    return index;
  }

  function buildStorePicker(cfg) {
    var i18n   = config.i18n;
    var stores = cfg.stores || [];
    var html   = '<div class="awpickupstore-store-picker mt-2">'
      + '<p class="awpickupstore-store-picker__label">'
      + '<i class="material-icons awpickupstore-icon">place</i> '
      + i18n.store_label
      + '</p>'
      + '<select class="form-control awpickupstore-store-select">'
      + '<option value="">' + i18n.store_placeholder + '</option>';

    stores.forEach(function (s) {
      html += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
    });

    html += '</select>'
      + '<p class="awpickupstore-store-info" style="display:none"></p>'
      + '<input type="hidden" name="awpickupstore_store_id">'
      + '</div>';

    return html;
  }

  function buildDatepicker(cfg) {
    var i18n = config.i18n;
    return '<div class="awpickupstore-appointment mt-2">'
      + '<p class="awpickupstore-appointment__label mb-2">'
      + '<i class="material-icons awpickupstore-icon">event</i> '
      + i18n.appointment_label
      + '</p>'
      + '<input type="text" class="form-control awpickupstore-datetime-picker" placeholder="' + i18n.date_placeholder + '" readonly>'
      + '<input type="hidden" name="awpickupstore_datetime">'
      + '</div>';
  }

  function buildHtml(cfg) {
    var html = '<div class="awpickupstore-extra">';

    if (cfg.message) {
      html += '<div class="awpickupstore-message alert alert-info mt-2 mb-2">' + cfg.message + '</div>';
    }

    if (cfg.show_store_picker) {
      html += buildStorePicker(cfg);
    }

    if (cfg.require_appointment) {
      html += buildDatepicker(cfg);
    }

    html += '</div>';
    return html;
  }

  /** Bind store select → show address info + populate hidden input. */
  function initStorePicker(container, cfg) {
    var select     = container.querySelector('.awpickupstore-store-select');
    var infoEl     = container.querySelector('.awpickupstore-store-info');
    var hiddenInput = container.querySelector('input[name="awpickupstore_store_id"]');
    if (!select || !infoEl || !hiddenInput) return;

    var storeIndex = buildStoreIndex(cfg.stores);

    select.addEventListener('change', function () {
      var id    = parseInt(select.value, 10);
      var store = storeIndex[id];
      hiddenInput.value = id || '';

      if (store && store.address) {
        infoEl.textContent  = store.address;
        infoEl.style.display = '';
      } else {
        infoEl.style.display = 'none';
      }
    });
  }

  /** Initialise Flatpickr with schedule constraints and minimum booking delay. */
  function initFlatpickr(container, cfg) {
    var pickerInput = container.querySelector('.awpickupstore-datetime-picker');
    var hiddenInput = container.querySelector('input[name="awpickupstore_datetime"]');
    if (!pickerInput || !hiddenInput || !window.flatpickr) return;

    var schedule      = cfg.schedule || {};
    var delayHours    = cfg.min_delay_hours || 0;

    // Earliest bookable moment = now + delay
    var minDateTime = new Date();
    minDateTime.setHours(minDateTime.getHours() + delayHours);

    /**
     * Return the minTime string ("HH:MM") to apply for a given selected date,
     * combining the schedule opening time and the booking delay for today.
     */
    function getMinTimeForDate(date) {
      var daySchedule = schedule[date.getDay()];
      if (!daySchedule) return null;

      var open = daySchedule.open;  // "HH:MM"

      // If the selected date is today, enforce the delay
      var today = new Date();
      if (date.toDateString() === today.toDateString()) {
        var delayStr = pad(minDateTime.getHours()) + ':' + pad(minDateTime.getMinutes());
        return delayStr > open ? delayStr : open;
      }

      return open;
    }

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    var fpOptions = {
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      minDate: minDateTime,   // Date object: Flatpickr enforces date + time for today
      time_24hr: true,
      onChange: function (selectedDates, dateStr, fp) {
        hiddenInput.value = dateStr;

        if (selectedDates.length) {
          var jsDay       = selectedDates[0].getDay();
          var daySchedule = schedule[jsDay];
          if (daySchedule) {
            fp.set('minTime', getMinTimeForDate(selectedDates[0]));
            fp.set('maxTime', daySchedule.close);
          } else {
            fp.set('minTime', null);
            fp.set('maxTime', null);
          }
        }
      },
    };

    // Disable days that are closed in the schedule, and today if delay pushes past closing time
    if (Object.keys(schedule).length) {
      fpOptions.disable = [function (date) {
        var daySchedule = schedule[date.getDay()];
        if (!daySchedule) return true;

        // Disable today if now + delay >= closing time
        var today = new Date();
        if (date.toDateString() === today.toDateString()) {
          var delayStr = pad(minDateTime.getHours()) + ':' + pad(minDateTime.getMinutes());
          return delayStr >= daySchedule.close;
        }

        return false;
      }];
    }

    // Apply French locale if available
    if (config.locale_iso === 'fr' && flatpickr.l10ns && flatpickr.l10ns.fr) {
      fpOptions.locale = flatpickr.l10ns.fr;
    }

    flatpickr(pickerInput, fpOptions);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
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

    if (cfg.show_store_picker) {
      initStorePicker(container, cfg);
    }

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
