<table class="ctable" border="0" cellspacing="0" cellpadding="2">

    <tr>
        <td></td>
        <td><b>{{#t}}common.zamovnyk{{/t}}</b></td>
        <td colspan="8">{{customer_name}}</td>
    </tr>
      {{#phone}}
    <tr>
        <td></td>
        <td>{{#t}}common.telefon{{/t}}</td>
        <td colspan="8">{{phone}}</td>
    </tr>
    {{/phone}}
    {{#email}}
    <tr>
        <td></td>
        <td>E-mail</td>
        <td colspan="8">{{email}}</td>
    </tr>
        {{/email}}
    <tr>
        <td></td>
        <td> {{#t}}common.typ_oplaty{{/t}}</td>
        <td colspan="8">{{paytypename}}</td>
        
    </tr>
   <tr>
        <td></td>
        <td> {{#t}}common.dostavka{{/t}}</td>
        <td>{{delivery}}</td>
        <td colspan="7">{{#t}}print.order.adresa{{/t}}</td>
    </tr>
    {{#isoutnumber}}
    <tr>
        <td></td>
        <td>{{#t}}common.zovnishnii_nomer{{/t}}</td>
        <td colspan="8" data-type="s">{{outnumber}}</td>
    </tr>

    {{/isoutnumber}}
   
    {{#isfirm}}
    <tr>

        <td></td>
        <td><b> {{#t}}common.prodavets{{/t}}</b></td>
        <td colspan="8"> {{firm_name}} </td>

    </tr>  
     {{#iban}}
    <tr>

        <td></td>
        <td> IBAN</td>
        <td colspan="8" data-type="s">{{iban}}   </td>

    </tr>
    {{/iban}}    
     
    {{/isfirm}}   
    
     <tr>
        <td colspan="10">{{{notes}}}</td>
    </tr>

    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="10" valign="middle">
            {{#t}}common.zamovlennia_vid{{/t}}
        </td>
    </tr>


    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}        </th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.od{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;"> </th>

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.kil{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.tsina{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right" valign="top">{{no}}</td>
        <td colspan="2">{{tovar_name}}</td>
        <td colspan="2" valign="top" data-type="s">{{tovar_code}}</td>
        <td valign="top">{{msr}}</td>
        <td valign="top">{{desc}}</td>

        <td align="right" valign="top">{{quantity}}</td>
        <td align="right" valign="top">{{price}}</td>
        <td align="right" valign="top">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="9" align="right">{{#t}}common.na_sumu_2{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>



    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="9" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}
    {{#delbonus}}
    <tr style="font-weight: bolder;">
        <td colspan="9" align="right">{{#t}}common.spysano_bonusiv{{/t}}</td>
        <td align="right">{{delbonus}}</td>
    </tr>
    {{/delbonus}}


   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="9" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="9" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  
 

    
</table>

