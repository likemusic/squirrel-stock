<table class="ctable" border="0" cellspacing="0" cellpadding="2">


 
    <tr>
      
        <td colspan="2" ><b>{{#t}}common.postachalnyk{{/t}}</b></td>
        <td colspan="4">{{customer_name}}</td>
    </tr>
   {{#edrpou}}
    <tr>
      <td></td>  
        <td   valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="4" data-type="s">{{edrpou}}</td>
    </tr>
     {{/edrpou}}      
    <tr>
  
        <td colspan="2" valign="top"><b>{{#t}}common.pokupets{{/t}}</b></td>
        <td colspan="4"> {{firm_name}}                    </td>

    </tr>
    {{#fedrpou}}
    <tr>
      <td></td> 
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="4" data-type="s">{{fedrpou}}</td>
    </tr>
     {{/fedrpou}}  
    {{#finn}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.ipn{{/t}}</td>
        <td colspan="4" data-type="s">{{finn}}</td>
    </tr>
     {{/finn}} 
     {{#isbank}}
    <tr>

        <td></td>
        <td> {{#t}}common.r_r{{/t}}</td>
        <td colspan="4">{{bankacc}}    {{bank}}</td>

    </tr>
    {{/isbank}}  
     {{#iban}}
    <tr>

        <td></td>
        <td data-type="s"> IBAN</td>
        <td colspan="4">{{iban}}   </td>

    </tr>
    {{/iban}}  

   <tr>
        <td colspan="6">{{{notes}}}</td>
    </tr>
      <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="6" valign="middle">
            {{#t}}print.retcustissue.povernennia_postachalnyku_vid{{/t}} <br>
        </td>
    </tr>  
    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>


        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td>{{tovar_name}}</td>
        <td data-type="s">{{tovar_code}}</td>


        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
    {{#nds}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.pdv{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{nds}}</td>
    </tr>
      {{/nds}}
   <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{payamount}}</td>
    </tr>
         {{#totalstr}}
    <tr>
        <td colspan="6">{{#t}}common.na_sumu{{/t}} <b>{{totalstr}}</b></td>
   </tr>
   {{/totalstr}}  
         <tr>
                        <td colspan="3" > 
                            {{#t}}print.goodsissue.prodavets{{/t}}
                        </td>
                        <td colspan="3"> 
                            {{#t}}print.goodsissue.pokupets{{/t}}
                        </td>

                    </tr>
</table>

