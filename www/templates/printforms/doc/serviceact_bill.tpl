<table class="ctable" border="0" cellpadding="1" cellspacing="0" >
    <tr>
        <td colspan="2">
          {{#isfinished}}  {{#t}}common.akt_vykonanykh_robit{{/t}} {{/isfinished}} 
          {{^isfinished}}  {{#t}}common.kvytantsiia_do{{/t}} {{/isfinished}} 
        
        
         {{document_number}}</td>
    </tr>
    <tr>

        <td colspan="2">{{#t}}common.vid_2{{/t}}</td>
    </tr>
    <tr>

        <td colspan="2"> {{firm_name}}</td>
    </tr>
    {{#shopname}}
    <tr>
        <td colspan="2"> {{shopname}}</td>
    </tr>
    {{/shopname}}
    <tr>

        <td colspan="2"> {{address}}</td>
    </tr>
    <tr>
        <td colspan="2"> {{phone}}</td>
    </tr>

    <tr>
        <td colspan="2"> {{customer_name}}</td>
    </tr>


    {{#isdevice}}
    <tr>
        <td colspan="2"> {{#t}}print.serviceact_bill.pryiniato_vid_kliienta{{/t}}</td>
    </tr>
    <tr>
        <td colspan="2"> {{device}}</td>
    </tr>
    <tr>
        <td colspan="2"> {{#t}}print.serviceact_bill.s_n{{/t}}</td>
    </tr>
    {{/isdevice}}

    {{#iswork}}

    <tr>
        <td colspan="2">{{#t}}print.serviceact_bill.roboty{{/t}}</td>
    </tr>
    {{#slist}}
    <tr>
        <td>{{service_name}}</td>
        <td align="right">{{amount}}</td>
    </tr>

    {{/slist}}
     {{/iswork}}  
  
    {{#isitems}}
   <tr>
        <td colspan="2">{{#t}}print.serviceact_bill.komplektuiuchi{{/t}}</td>
    </tr>
    {{#ilist}}
    <tr>
        <td>{{itemname}}</td>
        <td align="right">{{amount}}</td>
    </tr>

    {{/ilist}}
    {{/isitems}}
   
    {{#istotal}}
    <tr>
        <td align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{total}}</td>
    </tr>
    {{/istotal}}

 


    {{#ispay}}
    <tr>
        <td colspan="2">{{#t}}common.oplaty{{/t}}</td>
    </tr>
    {{#plist}}
    <tr>
        <td>{{pdate}}</td>
        <td align="right">{{ppay}}</td>
    </tr>

    {{/plist}}
    {{/ispay}}
    <tr>
        <td colspan="2"> {{gar}}</td>

    </tr>
    <tr>
        <td colspan="2"> <br></td>

    </tr>
    <tr>
        <td>{{#t}}common.vykonavets{{/t}}</td>
        <td>________</td>
    </tr>
    <tr>
        <td colspan="2"> <br></td>

    </tr>
    <tr>
        <td>{{#t}}common.kliient{{/t}}</td>
        <td>________</td>
    </tr>
</table>