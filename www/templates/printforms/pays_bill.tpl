<table class="ctable" border="0" cellpadding="1" cellspacing="0" >
    <tr>
        <td colspan="2"> <b>{{#t}}print.pays_bill.kvytantsiia_pro_oplatu{{/t}} </b></td>
    </tr>
    <tr>

        <td colspan="2">{{#t}}print.pays_bill.pidstava{{/t}}</td>
    </tr>
    <tr>

        <td colspan="2"> {{firm_name}}</td>
    </tr>



    <tr>
        <td colspan="2"> {{customer_name}}</td>
    </tr>


    <tr>
        <td colspan="2">{{#t}}common.oplaty{{/t}}</td>
    </tr>
    {{#plist}}
    <tr>
        <td>{{pdate}}</td>
        <td align="right">{{ppay}}</td>
    </tr>

    {{/plist}}

    <tr>
        <td  align="right"> <b> {{#t}}common.vsoho{{/t}}</b></td>
        <td  align="right"><b> {{pall}}</b></td>

    </tr>

</table>
<br>