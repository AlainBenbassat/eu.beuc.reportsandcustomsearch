{* membership summary *}
{if !empty($summaryTitle)}
  <div class="crm-block">
    <h1>{$summaryTitle}</h1>
    <table>
      {foreach from=$summaryData item=summaryRow}
        <tr>
          <td width="20%">{$summaryRow.membershipType}</td>
          <td>{$summaryRow.membershipCount}</td>
        </tr>
      {/foreach}
    </table>
  </div>
{/if}

{* default report template *}
{include file="CRM/Report/Form.tpl"}
