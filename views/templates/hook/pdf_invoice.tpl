<style>
  table#awpickupstore-pickup-tab {
    border: 1pt solid #000000;
  }
</style>

<br style="font-size:8px;line-height:8px;">
<table id="awpickupstore-pickup-tab" width="100%" cellpadding="6">
  <tr>
    <th colspan="2" class="header">
      {l s='Pickup appointment' d='Modules.Awpickupstore.Pdf_invoice'}
    </th>
  </tr>
  {if $awpickupstore_datetime}
  <tr>
    <td class="grey" width="50%">
      {l s='Date and time:' d='Modules.Awpickupstore.Pdf_invoice'}
    </td>
    <td class="white" width="50%">
      {$awpickupstore_datetime|escape:'html':'UTF-8'}
    </td>
  </tr>
  {/if}
  {if $awpickupstore_store_name}
  <tr>
    <td class="grey" width="50%">
      {l s='Collection point:' d='Modules.Awpickupstore.Pdf_invoice'}
    </td>
    <td class="white" width="50%">
      {$awpickupstore_store_name|escape:'html':'UTF-8'}
    </td>
  </tr>
  {/if}
</table>
