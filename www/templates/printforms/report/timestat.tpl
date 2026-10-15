<table border="0" class="ctable" cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="2">
            {{#t}}print.timestat.zvit_pro_robochyi_chas{{/t}}
        </td>
    </tr>
    <tr>

        <td align="center">
            <b> {{#t}}common.period_z_po{{/t}}   </b>
        </td>
    </tr>
    <tr>

        <td colspan="2">
            <b>  {{typename}}   </b>
        </td>
    </tr>


    {{#_detail}}
    <tr>

        <td>{{emp_name}}</td>
        <td class="text-end">{{tm}}</td>

    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">

        <td class="text-end">{{#t}}common.vsoho{{/t}}</td>

        <td class="text-end">{{total}}</td>

    </tr>

</table>


