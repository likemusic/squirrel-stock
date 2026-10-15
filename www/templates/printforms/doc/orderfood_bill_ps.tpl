<font bold="true">a</font>
 
<text>{{#t}}common.chek_2{{/t}}</text>
 
    
<font >a</font>
<text>{{#t}}common.vid_2{{/t}}</text>
<align>left</align>

    {{#fiscalnumber}}
<text>{{#t}}common.fiskalnyi_chek{{/t}}</text>
<text>{{#t}}common.fn_cheka{{/t}}</text>
    {{/fiscalnumber}}
   {{#fiscalnumberpos}}
<text>{{#t}}common.fn_rro{{/t}}</text>   
    {{/fiscalnumberpos}}

<text>{{firm_name}}</text>   
{{#inn}}  
<text>{{#t}}common.ipn_2{{/t}}</text>   
{{/inn}}  
{{#tin}}  
<text>{{#t}}common.iedrpou_2{{/t}}</text>   
{{/tin}}  
<text>{{shopname}}</text>   
  {{#shopname}}
<text>{{shopname}}</text>   
  {{/shopname}}
<text>{{address}}</text>    
 
    {{#customer_name}}
<text>{{#t}}common.pokupets_3{{/t}}</text>    

    {{/customer_name}}
    
    
   
    
        {{#form1}}
 <text>{{#t}}common.forma_oplaty_hotivka{{/t}}</text>        
        {{/form1}}
        {{#form2}}
 <text>{{#t}}common.forma_oplaty_bezhotivka{{/t}}</text>        
        {{/form2}}    
         {{#payeq}}
 <text>{{#t}}print.orderfood_bill_ps.zasib_oplaty{{/t}}  </text>        
 <text>{{payeq}}  </text>        
          
        {{/payeq}}   
        {{#trans}}
 <text>{{#t}}print.orderfood_bill_ps.tranzaktsii{{/t}}</text>        
          
        {{/trans}}     
<text>{{#t}}common.kasyr{{/t}}</text>    
  
 

<separator>-</separator>
    {{#_detail}}
<text>{{tovar_name}}</text>     
 
 <row>
  <col align="right" length="22" >{{quantity}}</col>
  <col align="right" length="10" >{{amount}}</col>
</row>
      {{#isstamps}}
       <text>{{#t}}common.aktsyzni_marky_2{{/t}}</text> 
 
        {{#stamps}}
          <text>{{name}}</text> 
        {{/stamps}}
    
    {{/isstamps}}
    {{/_detail}}
<separator>-</separator>
 <font bold="true">a</font>

<align>right</align>
<text>{{#t}}common.vsoho_2{{/t}}</text>
<font >a</font>
 

    {{^prepaid}}
    {{#isdisc}}
<text>{{#t}}common.znyzhka_3{{/t}}</text>
 
    {{/isdisc}}
   {{#bonus}}
 <text>{{#t}}print.orderfood_bill_ps.spysano_bonusiv{{/t}}</text>
 
    {{/bonus}}

 <text>{{#t}}common.do_splaty_3{{/t}}</text>
 
  
 
 <text>{{#t}}print.orderfood_bill_ps.oplata{{/t}}</text>
 {{#exchange}}
 <text>{{#t}}print.orderfood_bill_ps.reshta{{/t}}</text>
 {{/exchange}} 
 
    
     
    {{/prepaid}}
 {{#exciseval}}
 <text>{{#t}}print.orderfood_bill_ps.v_t_ch_aktsyz{{/t}}</text>
 
    {{/exciseval}}    
    {{#addbonus}}
 <text>{{#t}}print.orderfood_bill_ps.narakhovano_bonusiv{{/t}}</text>
 
    {{/addbonus}}
    {{#allbonus}}

 <text>{{#t}}print.orderfood_bill_ps.vsoho_bonusiv{{/t}}</text>
 
    {{/allbonus}}
    
{{#promo}}
<text> {{#t}}common.promokod{{/t}}</text>  
{{/promo}}  
<font bold="true">a</font>
<align>center</align>
<newline ></newline>

{{#checkslogan}}
<text>  {{checkslogan}}</text>
{{/checkslogan}}



  

{{#docqrcodeurl}}
<font >a</font>
<newline ></newline>
<qrcode type="code128"> {{docqrcodeurl}}</qrcode>
{{/docqrcodeurl}}

    
<newline ></newline>
 