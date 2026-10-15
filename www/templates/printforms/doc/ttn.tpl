<table class="ctable" border="0" cellspacing="0" cellpadding="2"  >


    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.pokupets{{/t}}</b></td>
     <td colspan="5"> {{customer_name}}  
          {{#phone}} {{#t}}common.tel_3{{/t}}  {{/phone}} 
        </td>
    </tr>
    {{#isfirm}}
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}common.prodavets{{/t}}</b></td>
      <td colspan="7"> {{firm_name}} 
        {{#fphone}} {{#t}}common.tel_4{{/t}}  {{/fphone}} 
          
        </td>

    </tr>
    {{/isfirm}}
    {{#isfop}}
    <tr>

        <td></td>
        <td><b> {{#t}}common.prodavets{{/t}}</b></td>
        <td colspan="5"> {{fop_name}} </td>

    </tr> 
      
   {{/isfop}} 
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.sklad{{/t}}</b></td>
        <td colspan="5">{{store_name}}</td>
    </tr>

    {{#order}}
    <tr>
        <td></td>
        <td><b>{{#t}}common.zamovlennia{{/t}}</b></td>
        <td colspan="5">{{order}}</td>
    </tr>
    {{/order}}
   {{#outnumber}}
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}print.goodsreceipt.zovn_nomer{{/t}}</b></td>
        <td colspan="6" data-type="s">{{outnumber}}</td>

    </tr>
    {{/outnumber}}
    
    <tr>
        <td></td>
        <td><b>{{#t}}common.telefon{{/t}}</b></td>
        <td colspan="5" data-type="s">{{phone}}</td>
    </tr>
 

    <tr>
        <td></td>
        <td><b>{{#t}}common.dostavka{{/t}}</b></td>
        <td>{{delivery_name}}</td>
        <td colspan="4">{{ship_address}}</td>
    </tr>

    {{#ship_number}}
    <tr>
        <td></td>
        <td><b>{{#t}}print.ttn.deklaratsii{{/t}}</b></td>
        <td colspan="4" data-type="s">{{ship_number}}</td>
    </tr>
    {{/ship_number}}
    <tr>
        <td></td>
        <td><b>{{#t}}print.ttn.data_vidpravky{{/t}}</b></td>
        <td colspan="5">{{sent_date}}</td>
    </tr>
    <tr>
        <td></td>
        <td><b>{{#t}}print.ttn.data_dostavky{{/t}}</b></td>
        <td colspan="5">{{delivery_date}}</td>
    </tr>
    <tr>
        <td></td>
        <td><b>{{#t}}common.vidpovidalnyi{{/t}}</b></td>
        <td colspan="5"> {{emp_name}}</td>
    </tr>
    {{#ship_amount}}
    <tr>
        <td></td>
        <td><b>{{#t}}print.ttn.vartist_dostavky{{/t}}</b></td>
        <td colspan="5"> {{ship_amount}}</td>
    </tr>
    {{/ship_amount}}
    <tr>
        <td colspan="7">{{{notes}}}</td>
    </tr>



<tr>
    <td style="font-weight: bolder;font-size: larger;" align="center" colspan="7" valign="middle">
        {{#t}}print.ttn.ttn_vid{{/t}} <br>
    </td>
</tr>

<tr style="font-weight: bolder;">
    <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
    <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
    <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
    <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>

    <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
    <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}</th>
    <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
</tr>
{{#_detail}}
<tr>
    <td align="right">{{no}}</td>
    <td data-type="s">{{tovar_name}}</td>
    <td>{{tovar_code}}</td>
    <td>{{msr}}</td>

    <td align="right">{{quantity}}</td>
    <td align="right">{{price}}</td>
    <td align="right">{{amount}}</td>
</tr>
{{/_detail}}
<tr style="font-weight: bolder;">
    <td style="border-top:1px #000 solid;" colspan="2">{{weight}}</td>
    <td style="border-top:1px #000 solid;" colspan="4" align="right">{{#t}}common.vsoho{{/t}}</td>
    <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
</tr>


</table>

