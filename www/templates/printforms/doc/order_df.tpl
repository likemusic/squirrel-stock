<table class="ctable" border="0" cellspacing="0" cellpadding="2">


  

    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="10" valign="middle">
            {{#t}}common.zamovlennia_vid{{/t}}
        </td>
    </tr>
     {{#isoutnumber}}
    <tr>
        <td></td>
        <td>{{#t}}common.zovnishnii_nomer{{/t}}</td>
        <td colspan="8">{{outnumber}}</td>
    </tr>

    {{/isoutnumber}}

    <tr style="font-weight: bolder;">
        
         <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}        </th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.od{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;"> </th>

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.kil{{/t}}</th>
        {{#isds}}
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}print.order_df.sklad_tsina{{/t}}</th>
         {{/isds}}
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.prod_tsina{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
     
        <td colspan="2">{{tovar_name}}</td>
        <td colspan="2" valign="top">{{tovar_code}}</td>
        <td valign="top">{{msr}}</td>
        <td valign="top">{{desc}}</td>

        <td align="right" valign="top">{{quantity}}</td>
         {{#isds}}
        <td align="right" valign="top">{{pricefrom}}</td>
         {{/isds}}
        <td align="right" valign="top">{{price}}</td>
        <td align="right" valign="top">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="7" align="right">{{#t}}common.na_sumu_2{{/t}}</td>
          {{#isds}}
        <td style="border-top:1px #000 solid;" align="right">{{totalfrom}}</td>
          {{/isds}}
        <td style="border-top:1px #000 solid;" align="right"> </td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
  
   <tr>
        <td></td>
        <td> {{#t}}common.dostavka{{/t}}</td>
        <td>{{delivery}}</td>
        <td colspan="7">{{#t}}print.order.adresa{{/t}}</td>
    </tr>
    <tr>
        <td colspan="10">{{{notes}}}</td>
    </tr>

 


 
 

    
</table>

