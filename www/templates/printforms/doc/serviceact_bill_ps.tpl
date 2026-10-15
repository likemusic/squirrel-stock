<align>center</align>
<font bold="true">a</font>
<text>
   {{#isfinished}}  {{#t}}common.akt_vykonanykh_robit{{/t}} {{/isfinished}} 
          {{^isfinished}}  {{#t}}common.kvytantsiia_do{{/t}} {{/isfinished}} 
         {{document_number}}
</text>
<font >a</font>
<text>{{#t}}common.vid_2{{/t}}</text>
<align>left</align>
<text> {{firm_name}}</text>
    {{#shopname}}
<text> {{shopname}}</text>
    {{/shopname}}
<text> {{address}}</text>    
<text>{{#t}}common.tel_3{{/t}}</text>    
<text> {{customer_name}}</text>    

    {{#isdevice}}
<text>{{#t}}print.serviceact_bill_ps.pryiniato_vid_kliienta{{/t}}</text>       
<text>{{device}}</text>       
<text>{{#t}}print.serviceact_bill.s_n{{/t}}</text>       
    {{/isdevice}}
    {{#iswork}}
<text>{{#t}}print.serviceact_bill.roboty{{/t}}</text>       
    {{#slist}}

<row>
  <col length="22" >{{service_name}}</col>
  <col align="right" length="10" >{{amount}}</col>
</row>
    {{/slist}}

   {{/iswork}}  
  
    {{#isitems}}
<text>{{#t}}print.serviceact_bill.komplektuiuchi{{/t}}</text>       
 
    {{#ilist}}
 
<row>
  <col length="22" >{{itemname}}</col>
  <col align="right" length="10" >{{amount}}</col>
</row>
    
    {{/ilist}}
    {{/isitems}}
   
    {{#istotal}}
<align>right</align>
<text>{{#t}}common.vsoho_2{{/t}}</text>
    {{/istotal}}


    {{#ispay}}
<align>left</align> 
<text>{{#t}}common.oplaty{{/t}}</text>  
 
    {{#plist}}
    
<row>
  <col length="22" >{{pdate}}</col>
  <col align="right" length="10" >{{ppay}}</col>
</row>    
    
 

    {{/plist}}
    {{/ispay}}
   
<text>{{gar}}</text>      
 <newline ></newline>   
 <text>{{#t}}print.serviceact_bill_ps.vykonavets{{/t}}</text>  
 <newline ></newline>  
 <text>{{#t}}print.serviceact_bill_ps.kliient{{/t}}</text>  
 <newline ></newline>  
 