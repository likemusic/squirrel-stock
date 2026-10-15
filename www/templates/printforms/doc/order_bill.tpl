<table class="ctable" border="0" cellpadding="1" cellspacing="0" {{{style}}}>
    <tr>
        <td colspan="3">{{#t}}common.zamovlennia_2{{/t}}</td>
    </tr>
    <tr>

        <td colspan="3">{{#t}}common.vid_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{#t}}common.prodavets_2{{/t}}</td>
    </tr>
    <tr>

        <td colspan="3"> {{firm_name}}</td>
    </tr>


    <tr>
        <td colspan="3"> {{phone}}</td>
    </tr>

    <tr>
        <td colspan="3"> {{#t}}common.pokupets_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{customer_name}}</td>
    </tr>


    <tr>
        <td colspan="3">{{#t}}print.order_bill.dostavka{{/t}}</td>

    </tr>
    <tr>
        <td colspan="3">{{ship_address}}</td>
    </tr>
    {{#_detail}}
    <tr>
        <td colspan="3">{{tovar_name}}</td>

    </tr>


    <tr>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr>
        <td colspan="2" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{total}}</td>
    </tr>


</table>