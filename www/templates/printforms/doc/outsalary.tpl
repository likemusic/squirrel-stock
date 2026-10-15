<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="2" align="center">
            <b> {{#t}}print.outsalary.vyplata_zarplaty_vid{{/t}}</b> <br>
        </td>
    </tr>


    <tr>
        <td colspan="2">
            <b>{{#t}}common.z_rakhunku{{/t}}</b> {{paymentname}}
        </td>
    </tr>

    <tr>
        <td colspan="2">
            <b>{{#t}}print.calcsalary.misiats{{/t}}</b> {{month}} {{year}}
        </td>
    </tr>
     {{#advance}}
  <tr>
        <td colspan="2">
            <b>{{#t}}doc.outsalary.vyplata_avansu{{/t}}</b> 
        </td>
    </tr>
    {{/advance}}
    <tr>
        <td colspan="2">
            {{{notes}}}
        </td>
    </tr>
    <tr>
        <td>
            <b>{{#t}}common.p_i_b{{/t}}</b>
        </td>
        <td class="text-end">
            <b>{{#t}}common.suma{{/t}}</b>
        </td>
    </tr>

    {{#_detail}}
    <tr>
        <td>
            {{emp_name}}
        </td>
        <td class="text-end">
            {{amount}}
        </td>
    </tr>

    {{/_detail}}
    <tr>
        <td>
            <b>{{#t}}common.vsoho{{/t}}</b>
        </td>
        <td class="text-end">
            <b>{{total}}</b>
        </td>
    </tr>

</table>


