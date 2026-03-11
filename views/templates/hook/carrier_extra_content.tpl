<div class="awpickupstore-extra">

  {if $awpickupstore_message}
    <div class="awpickupstore-message alert alert-info mt-2 mb-2">
      {$awpickupstore_message nofilter}
    </div>
  {/if}

  {if $awpickupstore_require_appointment}
    <div class="awpickupstore-appointment mt-2">
      <p class="awpickupstore-appointment__label mb-2">
        <i class="material-icons awpickupstore-icon">event</i>
        {l s='Choose your appointment date and time' mod='awpickupstore'}
      </p>
      <div class="row g-2">
        <div class="col-auto">
          <input
            type="date"
            name="awpickupstore_date"
            class="form-control"
            min="{$awpickupstore_min_date}"
            required
            aria-label="{l s='Appointment date' mod='awpickupstore'}"
          >
        </div>
        <div class="col-auto">
          <input
            type="time"
            name="awpickupstore_time"
            class="form-control"
            required
            aria-label="{l s='Appointment time' mod='awpickupstore'}"
          >
        </div>
      </div>
    </div>
  {/if}

</div>
