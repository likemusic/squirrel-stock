<table class="ctable" border="0" cellpadding="1" cellspacing="0" >
    <tr>
        <td align="center" colspan="2"> <h4>{{#t}}common.kh_zvit{{/t}} </h4></td>
    </tr>
    <tr>

        <td  > {{#t}}common.stvoreno{{/t}}</td> <td  > {{created_at}}</td>
         
    </tr>   
  <tr>
         <td  >  {{#t}}print.cb_xrep.kasa_na_pochatok{{/t}}</td> <td align="right" > {{initial}}</td>
    </tr>    
 
  <tr>
        <td  > <b>{{#t}}common.realizatsiia{{/t}}</b></td>        <td  >  </td>
    </tr>    
    <tr>
        <td  > {{#t}}common.chekiv{{/t}}  </td>        <td  align="right"> {{cnt}}</td>
    </tr>    
   <tr>
        <td  > {{#t}}common.oplaty{{/t}}</td>        <td  >  </td>
    </tr>  
     {{#pays}} 
    <tr>
        <td  >  {{label}}</td>        <td align="right" > {{sum}}</td>
    </tr>  
      {{/pays}}   
 
  <tr>
        <td  > <b>{{#t}}print.vk_xrep.povernenniaz{{/t}}</b></td>        <td  >  </td>
    </tr>    
    <tr>
        <td  > {{#t}}common.chekiv{{/t}}  </td>        <td align="right" > {{rcnt}}</td>
    </tr>    
   <tr>
        <td  > {{#t}}common.oplaty{{/t}}</td>        <td  >  </td>
    </tr>  
     {{#rpays}} 
    <tr>
        <td  >  {{label}}</td>        <td align="right" > {{sum}}</td>
    </tr>  
      {{/rpays}}   
 
    <tr>
         <td  > {{#t}}common.balans{{/t}}</td> <td align="right" > {{balance}}</td>
    </tr>     
 

</table>
<br>