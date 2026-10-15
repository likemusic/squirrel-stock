<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="4">
            {{#t}}print.itemorder.zamovleni_tovary_na{{/t}}
        </td>
    </tr>
    {{#cust}}
    <tr>

        <td colspan="4">
            <b> {{#t}}common.pokupets_2{{/t}}</b> {{customer_name}}
        </td>
    </tr>
    {{/cust}}
    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px"> {{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px"> {{#t}}common.artykul{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.od{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>


    </tr>
    {{#_detail}}
    <tr>


        <td>{{name}}</td>
        <td>{{code}}</td>

        <td>{{msr}}</td>
        <td align="right">{{qty}}</td>


    </tr>
    {{/_detail}}


    <tr>

        <td colspan="4">
            <b> {{#t}}common.na_sumu_2{{/t}}</b> {{total}}
        </td>
    </tr>

</table>
<br> <br>

