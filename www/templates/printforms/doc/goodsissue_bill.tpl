<table class="ctable" border="0" cellpadding="1" cellspacing="0" >
    <tr>
        <td colspan="3">{{#t}}print.goodsissue_bill.nakladna{{/t}}</td>
    </tr>
    <tr>

        <td colspan="3">{{#t}}common.vid_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{#t}}common.prodavets_2{{/t}}</td>
    </tr>
    <tr>

        <td colspan="2"> {{firm_name}}</td>
    </tr>
 
    {{#isbank}}
   <tr>

        <td colspan="2">    {{bank}} </td>
    </tr>
  <tr>

        <td colspan="2">{{#t}}print.goodsissue_bill.r_r{{/t}}   </td>
    </tr>
    {{/isbank}}

    <tr>
        <td colspan="3"> {{#t}}common.tel_3{{/t}}</td>
    </tr>
    {{#customer_name}}
    <tr>
        <td colspan="3"> {{#t}}common.pokupets_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{customer_name}}</td>
    </tr>

    {{/customer_name}}


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
    <tr>
                        <td colspan="3" > 
                            {{{docbarcode}}}
                        </td>
       </tr>                 
   

</table>