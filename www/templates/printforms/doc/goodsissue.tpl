<table class="ctable  " border="0" cellspacing="0" cellpadding="2"  >

     {{#customer_name}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.pokupets{{/t}}</b></td>
 
        <td colspan="6"> {{customer_name}}</b> 
          {{#phone}} {{#t}}common.tel_3{{/t}}  {{/phone}} 
        </td>
    </tr>
      {{/customer_name}} 
      
  
 
   {{#edrpou}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="6" data-type="s">{{edrpou}}</td>
    </tr>
     {{/edrpou}}       
      
      {{#iscustaddress}}
    
      <tr>
        <td></td>
        <td valign="top">{{#t}}common.adresa_2{{/t}}</td>
        <td colspan="6">{{custaddress}}</td>
    </tr>    
    {{/iscustaddress}}      
      
      
    {{#isfirm}}
    <tr>
        <td></td>

        <td valign="top"><b>{{#t}}common.prodavets{{/t}}</b></td>
       <td colspan="6"> {{firm_name}} 
        {{#fphone}} {{#t}}common.tel_4{{/t}}  {{/fphone}} 
          
        </td>

    </tr>
   {{#fedrpou}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="6"  data-type="s">{{fedrpou}}</td>
    </tr>
     {{/fedrpou}}  
    {{#finn}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.ipn{{/t}}</td>
        <td colspan="6" data-type="s">{{finn}}</td>
    </tr>
     {{/finn}}           
   
      
    {{/isfirm}}
    {{#isfop}}
    <tr>

        <td></td>
        <td><b> {{#t}}common.prodavets{{/t}}</b></td>
        <td colspan="6"> {{fop_name}} </td>

    </tr> 
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.iedrpou{{/t}}</td>
        <td colspan="6" data-type="s">{{fop_edrpou}}</td>
    </tr>       
   {{/isfop}}    
   
     <tr>
        <td></td>
        <td valign="top">{{#t}}common.adresa_2{{/t}}</td>
        <td colspan="6">{{address}}</td>
    </tr>      
    {{#isbank}}
    <tr>

        <td></td>
        <td> {{#t}}common.r_r{{/t}}</td>
        <td colspan="6">{{bankacc}}    {{bank}}</td>

    </tr>
    {{/isbank}}  
     {{#iban}}
    <tr>

        <td></td>
        <td> IBAN</td>
        <td colspan="6"  data-type="s">{{iban}}   </td>

    </tr>
    {{/iban}}     
    {{#iscontract}}
    <tr>

        <td></td>

        <td valign="top"><b>{{#t}}common.dohovir{{/t}}</b></td>
        <td colspan="6">{{#t}}common.vid{{/t}}</td>


    </tr>
    {{/iscontract}}
    
    <tr>
        <td></td>
        <td valign="top"><br>{{#t}}print.goodsissue.spysano_z{{/t}}</td>
        <td colspan="6"><br>{{store_name}}</td>
    </tr>
 
    {{#order}}
    <tr>
        <td></td>
        <td><b>{{#t}}common.zamovlennia{{/t}}</b></td>
        <td colspan="6">{{order}}</td>
    </tr>
    {{/order}}
    <tr>
        <td colspan="8">{{{notes}}}</td>
    </tr>


    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="8" valign="middle">
            {{#t}}print.goodsissue.vydatkova_nakladna_vid{{/t}} <br>
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>

        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}print.goodsissue.zn{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}  {{#nds}} {{#t}}common.bez_pdv{{/t}}  {{/nds}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td>{{tovar_name}}</td>
        <td data-type="s">{{tovar_code}}</td>
        <td>{{msr}}</td>

        <td align="right">{{quantity}}</td>
        <td align="right">{{disc}}</td>
        <td align="right">{{price}}  {{#nds}} ({{pricenonds}})  {{/nds}} </td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="2">{{weight}}</td>

        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.na_sumu_2{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>

    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}
   {{#nds}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.v_t_ch_pdv{{/t}}</td>
        <td align="right">{{nds}}</td>
    </tr>
    {{/nds}}

   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
     {{#isprep}}  
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.peredplata_2{{/t}}</td>
        <td align="right">{{prepaid}}</td>
    </tr>
     {{/isprep}}      
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="7" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  
       {{#payamount}}
   
   {{#totalstr}}
    <tr>
        <td colspan="8">{{#t}}common.na_sumu{{/t}} <b>{{totalstr}}</b></td>
   </tr>
   {{/totalstr}}                    

              {{/payamount}} 
                    <tr>
                        <td colspan="4" > 
                            {{#t}}print.goodsissue.prodavets{{/t}}
                        </td>
                        <td colspan="4"> 
                            {{#t}}print.goodsissue.pokupets{{/t}}
                        </td>

                    </tr>
                    <tr>
                        <td> </td>
                        <td colspan="7">
                            {{#isstamp}}
                            <img style="height:100px;" src="{{stamp}}"/>
                            {{/isstamp}}

                            {{^isstamp}}
                        
                            <br><br>
                            {{#t}}print.goodsissue.nbsp_nbsp_nbsp_nbsp_nbsp{{/t}}
                            <br><br>
                            {{/isstamp}}
                        </td>


                    </tr>
          
              
                          
                    </table>

