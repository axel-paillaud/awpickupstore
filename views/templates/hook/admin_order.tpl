<div class="card mt-2">
  <div class="card-header">
    <i class="material-icons">event_available</i>
    {l s='Pickup appointment' mod='awpickupstore'}
  </div>
  <div class="card-body">
    {if $awpickupstore_datetime}
      <p class="mb-1">
        <i class="material-icons" style="font-size:1rem;vertical-align:middle">schedule</i>
        <strong>{$awpickupstore_datetime|escape:'html':'UTF-8'}</strong>
      </p>
    {/if}
    {if $awpickupstore_store_name}
      <p class="mb-0">
        <i class="material-icons" style="font-size:1rem;vertical-align:middle">place</i>
        {$awpickupstore_store_name|escape:'html':'UTF-8'}
      </p>
    {/if}
  </div>
</div>
