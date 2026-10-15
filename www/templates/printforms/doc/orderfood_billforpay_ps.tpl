<font bold="true">a</font>
 
<text>{{#t}}print.orderfood_billforpay.rakhunok{{/t}}</text>
  
    
<font >a</font>
<text>{{#t}}common.vid_2{{/t}}</text>
<align>left</align>

 

<text>{{firm_name}}</text>   
   
 
<text>{{address}}</text>    
 
    {{#customer_name}}
<text>{{#t}}common.pokupets_3{{/t}}</text>    

    {{/customer_name}}
    
 
 

<separator>-</separator>
    {{#_detail}}
<text>{{tovar_name}}</text>     
 
 <row>
  <col align="right" length="22" >{{quantity}}</col>
  <col align="right" length="10" >{{amount}}</col>
</row>
 
    {{/_detail}}
<separator>-</separator>
<font bold="true">a</font>

<align>right</align>
<text>{{#t}}common.vsoho_2{{/t}}</text>
<font >a</font>
 

 
    {{#isdisc}}
<text>{{#t}}common.znyzhka_3{{/t}}</text>
 
    {{/isdisc}}
   {{#bonus}}
 <text>{{#t}}print.orderfood_bill_ps.spysano_bonusiv{{/t}}</text>
 
    {{/bonus}}

 <text>{{#t}}common.do_splaty_3{{/t}}</text>
 
    
 
<newline ></newline>
 