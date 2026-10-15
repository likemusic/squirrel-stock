 
   <table class="ctable" border="0" cellpadding="1" cellspacing="0"  }>
    <tr>
        <td colspan="2" style="font-weight:bolder;text-align:center" >{{#t}}print.zrep.z_zvit{{/t}}</td>
    </tr>
   
   {{#test}}
   <tr>
        <td colspan="2" style=" text-align:center" >{{#t}}common.testovyi_rezhym{{/t}}</td>
    </tr>
   {{/test}}
    
    <tr>

        <td colspan="2"  > {{#t}}print.zrep.data{{/t}}</td>
    </tr>
    <tr>

        <td  colspan="2"> {{firm}} </td>
         
    </tr>
    <tr>

        <td  colspan="2"> {{{address}}} </td>
         
    </tr>
  <tr>

        <td  > {{#t}}print.zrep.iedrpou_ipn{{/t}}</td>
        <td  > {{inn}} </td>
    </tr>
    <tr>

        <td  > {{#t}}print.zrep.fn_term{{/t}}</td>
        <td  > {{fnpos}} </td>
    </tr>
    <tr>

        <td  > {{#t}}print.zrep.fn_dok{{/t}}</td>
        <td  > {{fndoc}} </td>
    </tr>

   <tr>

        <td  > <b>{{#t}}app.eqentry.prodazh{{/t}}</b></td>
        <td  >  </td>
    </tr>
    {{#payments}}
      <tr>

        <td  >{{forma}}</td>
        <td style="text-align:right" > {{amount}} </td>
    </tr> 
    {{/payments}}
   <tr>

        <td  >{{#t}}print.zrep.kilkist_chekiv{{/t}}</td>
        <td style="text-align:right" > {{cnt}} </td>
    </tr>
 
   <tr>

        <td  > <b>{{#t}}common.povernennia{{/t}}</b></td>
        <td  >  </td>
    </tr>
    {{#rpayments}}
      <tr>

        <td  >{{forma}}</td>
        <td style="text-align:right" > {{amount}} </td>
    </tr> 
    {{/rpayments}}    
   <tr>

        <td  >{{#t}}print.zrep.kilkist_chekiv{{/t}}</td>
        <td  style="text-align:right" > {{rcnt}} </td>
    </tr>
 
    
    
       
    </table>
 