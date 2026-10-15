<table class="ctable" border="0" cellspacing="0" cellpadding="2">


    <tr>
        <td></td>
        <td>{{#t}}common.postachalnyk{{/t}}</td>
        <td colspan="5">{{customer_name}}</td>
    </tr>
    {{#iscontract}}
    <tr>

        <td></td>

        <td valign="top"><b>{{#t}}common.dohovir{{/t}}</b></td>
        <td colspan="5">{{#t}}common.vid{{/t}}</td>


    </tr>
    {{/iscontract}} 
    
    {{#ispayreq}}
    <tr>

        <td></td>

        <td colspan="6"> {{#t}}print.goodsreceipt.platizhni_rekvizyty{{/t}}<br>  {{payreq}}  </td>

    </tr>
    {{/ispayreq}}    
       
    {{#isfirm}}
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}common.pokupets{{/t}}</b></td>
        <td colspan="5">{{firm_name}}</td>

    </tr>
    {{/isfirm}}

    {{#isval}}
    <tr style="font-weight: bolder;">
       <td></td>
        <td><b>{{#t}}common.valiuta{{/t}}</b></td>
        <td colspan="5">{{valname}}   ({{rate}})</td>
        
        
    </tr>
    {{/isval}}
   <tr>
        <td colspan="7">{{{notes}}}</td>
    </tr>
            
    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="7" valign="middle">
            <br> {{#t}}print.invoicecust.rakhunok_vkhidnyi_vid{{/t}} <br><br>
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}print.invoicecust.kod_prodavtsia{{/t}}</th>
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
        <td data-type="s">{{custcode}}</td>
        <td>{{msr}}</td>
        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="7" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
    {{#isdisc}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{disc}}</td>
    </tr>
    {{/isdisc}}
    {{#isnds}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.pdv{{/t}}</td>
        <td align="right">{{nds}}</td>
    </tr>
     {{/isnds}}


   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  


</table>

