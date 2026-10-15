<table class="ctable" border="0" cellspacing="0" cellpadding="2">


    <tr>
        <td></td>
        <td><b>{{#t}}common.postachalnyk{{/t}}</b></td>
        <td colspan="6">{{customer_name}}</td>
    </tr>
    {{#iscontract}}
    <tr>

        <td></td>

        <td valign="top"><b>{{#t}}common.dohovir{{/t}}</b></td>
        <td colspan="6">{{#t}}common.vid{{/t}}</td>


    </tr>
    {{/iscontract}}    
    {{#ispayreq}}
    <tr>

        <td></td>

        <td colspan="7"> {{#t}}print.goodsreceipt.platizhni_rekvizyty{{/t}}<br>  {{payreq}}  </td>

    </tr>
    {{/ispayreq}}      
   {{#outnumber}}
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}print.goodsreceipt.zovn_nomer{{/t}}</b></td>
        <td colspan="6" data-type="s">{{outnumber}}</td>

    </tr>
    {{/outnumber}}    
    {{#isfirm}}
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}common.pokupets{{/t}}</b></td>
        <td colspan="6">{{firm_name}}</td>

    </tr>
    {{/isfirm}}

 
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}common.na_sklad{{/t}}</b></td>
        <td colspan="6">{{storename}}</td>

    </tr>
   {{#storeemp}}
    <tr>
  
     <td></td>

        <td valign="top"><b>{{#t}}common.na_spivrobitnyka{{/t}}</b></td>
        <td colspan="6">{{storeemp}}</td>
  
       
    </tr>
 
    {{/storeemp}}      
    <tr>
        <td></td>
        <td><b>{{#t}}common.pidstava_2{{/t}}</b></td>
        <td colspan="6">{{basedoc}}</td>
    </tr>
    <tr>
        <td colspan="8">{{{notes}}}</td>
    </tr>
    
     {{#isval}}
    <tr style="font-weight: bolder;">
       <td></td>
        <td><b>{{#t}}common.valiuta{{/t}}</b></td>
        <td colspan="6">{{valname}}   ({{rate}})</td>
        
        
    </tr>
    {{/isval}}    
    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="8" valign="middle">
            <br> {{#t}}print.goodsreceipt.prybutkova_nakladna_vid{{/t}} <br><br>
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.artykul{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.shtrykh_kod_2{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kod_post{{/t}}</th>

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>

        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td>{{itemname}}</td>
        <td data-type="s">{{itemcode}}</td>
        <td data-type="s">{{barcode}}</td>
        <td data-type="s">{{custcode}}</td>

        <td>{{msr}}</td>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="8" align="right">{{#t}}common.na_sumu_2{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
 
    {{#isdisc}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{disc}}</td>
    </tr>
    {{/isdisc}}
    {{#isnds}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.pdv{{/t}}</td>
        <td align="right">{{nds}}</td>
    </tr>
    {{/isnds}}
   {{#delivery}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}doc.goodsreceipt.dostavka{{/t}}</td>
        <td align="right">{{delivery}}</td>
    </tr>
    {{/delivery}}
 
   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
  
     {{#isprep}}  
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.peredplata_2{{/t}}</td>
        <td align="right">{{prepaid}}</td>
    </tr>
     {{/isprep}}    
  
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  

     
</table>

