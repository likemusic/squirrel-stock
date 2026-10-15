<table class="ctable" border="0" cellspacing="0" cellpadding="2">

    {{#islogo}}
    <tr>

        <td colspan="9">
            <img style="height:100px;" src="{{logo}}"/>
        </td>

    </tr>
    {{/islogo}}
    <tr>
        <td></td>
        <td><b>{{#t}}common.pokupets{{/t}}</b></td>
        <td colspan="7"> {{customer_name}}</b> 
          {{#phone}} {{#t}}common.tel_3{{/t}}  {{/phone}} 
        </td>
    </tr>
    
 
 
   {{#edrpou}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="7">{{edrpou}}</td>
    </tr>
     {{/edrpou}}       
          
     {{#iscustaddress}}
    
      <tr>
        <td></td>
        <td valign="top">{{#t}}common.adresa_2{{/t}}</td>
        <td colspan="7">{{custaddress}}</td>
    </tr>    
    {{/iscustaddress}}   
    
    {{#isfirm}}
    <tr>

        <td></td>
        <td><b> {{#t}}common.prodavets{{/t}}</b></td>
        <td colspan="7"> {{firm_name}} 
        {{#fphone}} {{#t}}common.tel_4{{/t}}  {{/fphone}} 
          
        </td>

    </tr>
 
    {{#fedrpou}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="7">{{fedrpou}}</td>
    </tr>
     {{/fedrpou}}  
    
     {{#finn}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.ipn{{/t}}</td>
        <td colspan="7">{{finn}}</td>
    </tr>
     {{/finn}}     
  
    {{/isfirm}}
    {{#isfop}}
    <tr>

        <td></td>
        <td><b> {{#t}}common.prodavets{{/t}}</b></td>
        <td colspan="7"> {{fop_name}} </td>

    </tr> 
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="7">{{fop_edrpou}}</td>
    </tr>       
   {{/isfop}}   
     <tr>
        <td></td>
        <td valign="top">{{#t}}common.adresa_2{{/t}}</td>
        <td colspan="7">{{address}}</td>
    </tr>    
    {{#iscontract}}
    <tr>

        <td></td>
        <td> {{#t}}common.dohovir{{/t}}</td>
        <td colspan="7">{{#t}}common.vid{{/t}}</td>

    </tr>
    {{/iscontract}}
    {{#isbank}}
    <tr>

        <td></td>
        <td> {{#t}}common.r_r{{/t}}</td>
        <td colspan="7">{{bankacc}}    {{bank}}</td>

    </tr>
    {{/isbank}}
     {{#iban}}
    <tr>

        <td></td>
        <td> IBAN</td>
        <td colspan="7">{{iban}}   </td>

    </tr>
    {{/iban}}
   <tr>
        <td colspan="9">{{{notes}}}</td>
    </tr>
    
    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="9" valign="middle">
            {{#t}}print.invoice.rakhunok_faktura_vid{{/t}}
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}
        </th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.od{{/t}}</th>

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.kil{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.tsina{{/t}}  {{#nds}} {{#t}}common.bez_pdv{{/t}}  {{/nds}} </th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td colspan="2">{{tovar_name}}</td>
        <td colspan="2">{{tovar_code}}</td>
        <td>{{msr}}</td>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}  {{#nds}} ({{pricenonds}})  {{/nds}}   </td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="8" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}
   {{#nds}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.v_t_ch_pdv{{/t}}</td>
        <td align="right">{{nds}}</td>
    </tr>
    {{/nds}}
   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="8" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  
       {{#payamount}}
   {{#totalstr}}
    <tr>
        <td colspan="9">{{#t}}common.na_sumu{{/t}} <b>{{totalstr}}</b></td>
   </tr>
   {{/totalstr}}      
  {{/payamount}}
    <tr>                  <td colspan="5">
                            {{#isstamp}}
                            <img style="height:100px;" src="{{stamp}}"/>
                            {{/isstamp}}


                        </td>
                        <td colspan="4">
                            {{#issign}}
                            <img style="height:100px;" src="{{sign}}"/>
                            {{/issign}}


                        </td>

                    </tr>
     <tr>               <td> </td>
        <td colspan="8" aliqn="center">
                            {{#qr}}
                               {{{qr}}} 
                            {{/qr}}


                        </td>
                        
       
          </tr>                     
                    </table>

