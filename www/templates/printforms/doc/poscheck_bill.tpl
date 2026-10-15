<table class="ctable" border="0" cellpadding="1" cellspacing="0"  {{{style}}}}>
    <tr style="font-weight: bolder;">
        <td colspan="3">{{#t}}common.chek_2{{/t}}</td>
    </tr>
    {{#fiscalnumber}}
    <tr>
        <td colspan="3">{{#t}}common.fiskalnyi_chek{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3">{{#t}}common.fn_cheka{{/t}}</td>
    </tr>
    {{/fiscalnumber}}
   {{#fiscalnumberpos}}
    <tr>
        <td colspan="3">{{#t}}common.fn_rro{{/t}}</td>
    </tr>
    {{/fiscalnumberpos}}
   

    <tr>

        <td colspan="3">{{#t}}common.vid_3{{/t}}</td>
    </tr>
    <tr>

        <td colspan="3"> {{firm_name}}</td>
    </tr>
  {{#inn}}
    <tr>

        <td colspan="3">{{#t}}common.ipn_2{{/t}}</td>
    </tr>
 {{/inn}} 
 {{#tin}}
    <tr>

        <td colspan="3">{{#t}}common.iedrpou_2{{/t}}</td>
    </tr>
 {{/tin}} 
    
    {{#shopname}}
    <tr>
        <td colspan="3"> {{shopname}}</td>
    </tr>
    {{/shopname}}
    <tr>

        <td colspan="3"> {{address}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{phone}}</td>
    </tr>
    {{#customer_name}}
    <tr>
        <td colspan="3"> {{#t}}common.pokupets_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{customer_name}}</td>
    </tr>

    {{/customer_name}}
    <tr>
       <td colspan="3" > 
        {{#form1}}
          {{#t}}common.forma_oplaty_hotivka{{/t}}
        {{/form1}}
        {{#form2}}
          {{#t}}common.forma_oplaty_bezhotivka{{/t}}
        {{/form2}}
        {{#form3}}
          {{#t}}print.poscheck_bill.forma_oplaty_inshe{{/t}}
        {{/form3}}

       </td>
    </tr>  
  {{#payeq}}
    <tr>
       <td colspan="3" > 
          {{#t}}print.orderfood_bill.zasib_oplaty{{/t}}
       </td>
    </tr> 
  {{/payeq}}       
   {{#trans}}
    <tr>
       <td colspan="3" > 
          {{#t}}print.orderfood_bill.tranzaktsii{{/t}}
       </td>
    </tr> 
  {{/trans}}   
    <tr>
        <td colspan="3">{{#t}}common.terminal_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3">{{#t}}common.kasyr{{/t}}</td>
    </tr>
    {{#_detail}}
    <tr>
        <td  colspan="3">{{tovar_name}}</td> 
    </tr>
    <tr>
        <td    align="right">{{quantity}}</td>
        <td    align="right">{{price}}</td>
        <td    align="right">{{amount}}</td>
    </tr>  
       {{#isstamps}}
    <tr>
        <td  colspan="3">{{#t}}common.aktsyzni_marky_2{{/t}}</td> 
    </tr> 
        {{#stamps}}
        <tr>
            <td  colspan="3">   {{name}}</td> 
        </tr> 
        {{/stamps}}
    
    {{/isstamps}}

    
    
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{total}}</td>
    </tr>
     {{#exciseval}}
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}print.orderfood_bill.v_t_ch_aktsyz{{/t}}</td>
        <td align="right">{{exciseval}}</td>
    </tr>
    {{/exciseval}}
 
    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}
    {{#delbonus}}
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}print.poscheck_bill.spysano_bonusiv{{/t}}</td>
        <td align="right">{{delbonus}}</td>
    </tr>
    {{/delbonus}}
   {{#prepaid}}
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.peredplata_2{{/t}}</td>
        <td align="right">{{prepaid}}</td>
    </tr>
    {{/prepaid}}

    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.reshta{{/t}}</td>
        <td align="right">{{exchange}}</td>
    </tr>
  

  

  
         
   {{#addbonus}}
   <tr  >
        <td colspan="2" align="right">{{#t}}print.poscheck_bill.narakhovano_bonusiv{{/t}}</td>
        <td align="right">{{addbonus}}</td>
    </tr>
   {{/addbonus}}    
  {{#allbonus}}
   <tr  >
        <td colspan="2" align="right">{{#t}}print.poscheck_bill.vsoho_bonusiv{{/t}}</td>
        <td align="right">{{allbonus}}</td>
    </tr>
   {{/allbonus}}          
     
    {{#promo}}   
    <tr style="font-weight: bolder;">
        <td colspan="3">{{#t}}common.promokod{{/t}}</td>

    </tr>
   {{/promo}}   
   
   {{#checkslogan}}   
    <tr style="font-weight: bolder;">
        <td colspan="3"><br>{{checkslogan}}</td>

    </tr>
   {{/checkslogan}}   
 
    
    
    
{{#isdocqrcode}}                
       <tr>                    
                        <td colspan="3"> 
                            {{{docqrcode}}}
                        </td>

                    </tr>    
{{/isdocqrcode}}                    
</table>