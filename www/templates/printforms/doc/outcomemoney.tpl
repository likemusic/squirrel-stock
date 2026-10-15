<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="4" align="center">
            <b> {{#t}}print.outcomemoney.vydatkovyi_order_vid{{/t}}</b> <br>
        </td>
    </tr>


    <tr>
        <td colspan="4">
            <b>{{#t}}common.z_rakhunku{{/t}}</b> {{from}}
        </td>
    </tr>
    <tr>
        <td colspan="4">
            <b>{{#t}}common.suma_2{{/t}}</b> {{amount}} 
        </td>
    </tr>
    <tr>
        <td colspan="4">
            {{totalstr}}   
        </td>
    </tr>
  
    {{#customer}}
    <tr>
        <td colspan="4">
            <b>{{#t}}common.kontrahent_2{{/t}}</b> {{customer}}
        </td>
    </tr>
    {{/customer}}
    {{#contract}}
    <tr>
        <td colspan="4">
            <b>{{#t}}print.incomemoney.dohovir{{/t}}</b> {{contract}}
        </td>
    </tr>
    {{/contract}}
    {{#emp}}
    <tr>
        <td colspan="4">
            <b>{{#t}}common.spivrobitnyk_2{{/t}}</b> {{emp}}
        </td>
    </tr>
    {{/emp}}
    <tr>
        <td colspan="4">
            {{{notes}}}
        </td>
    </tr>


</table>


