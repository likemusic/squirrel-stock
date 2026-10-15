<table class="ctable" border="0" cellspacing="0" cellpadding="2" >


    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="7" valign="middle">
            {{#t}}print.poscheck.chek_vid{{/t}} <br>
        </td>
    </tr>
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.prodavets{{/t}}</b></td>
        <td colspan="5">{{firm_name}}</td>
    </tr>
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.ipn{{/t}}</b></td>
        <td colspan="5" data-type="s">{{inn}}</td>
    </tr>
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.telefon{{/t}}</b></td>
        <td colspan="5">{{phone}}</td>
    </tr>
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.terminal{{/t}}</b></td>
        <td colspan="5" data-type="s">{{pos_name}}</td>
    </tr>
    {{#fiscalnumber}}
 
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}print.orderfood.fn_cheka{{/t}}</b></td>
        <td colspan="5" data-type="s">{{fiscalnumber}}</td>    
    
        
    </tr>
    {{/fiscalnumber}}    
    {{#customer_name}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.pokupets{{/t}}</b></td>
        <td colspan="5">{{customer_name}}</td>
    </tr>
    {{/customer_name}}
   <tr>
        <td colspan="7">{{{notes}}}</td>
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
        <td>{{tovar_name}}</td>
        <td data-type="s">{{tovar_code}}</td>
        <td>{{msr}}</td>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="6" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>

 
    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}
   {{#prepaid}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.peredplata_2{{/t}}</td>
        <td align="right">{{prepaid}}</td>
    </tr>
    {{/prepaid}} 

   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  
      {{#exchange}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.reshta{{/t}}</td>
        <td align="right">{{exchange}}</td>
    </tr>
     {{/exchange}}  
  
</table>

