<table class="ctable" border="0" cellpadding="1" cellspacing="0" >
   
    <tr>
        <td  > <b>{{#t}}print.prro_xrep.fop{{/t}}</b></td>        <td colspan="2"  > {{firmname}} </td>         
    </tr> 
   <tr>
        <td  > <b>{{#t}}common.iedrpou{{/t}}</b></td>        <td colspan="2" > {{tin}} </td>        
    </tr> 
    <tr>  
        <td  > <b>{{#t}}print.prro_xrep.torhova_tochka{{/t}}</b></td>        <td colspan="2"  >{{pointname}}  </td>     
    </tr>   
   <tr>  
        <td  > <b>{{#t}}common.adresa_2{{/t}}</b></td>        <td colspan="2"  >  {{address}}</td>       
    </tr>   
  <tr>  
        <td  > <b>{{#t}}common.terminal{{/t}}</b></td>        <td colspan="2"  >{{posnumber}}  </td>    
    </tr>   
   
    <tr>
        <td align="center" colspan="3"> <h4>{{#t}}common.kh_zvit{{/t}} </h4></td>
    </tr>
    <tr>

        <td  > {{#t}}common.data{{/t}}</td> <td colspan="2" > {{created_at}}</td>      
        
    </tr>    
   <tr>

        <td  > <td colspan="3" > &nbsp;</td>      
        
    </tr> 
    
    
      {{#sin}} 
    <tr>
        <td  > {{#t}}print.cb_xrep.sduzhbove_vnesennia{{/t}}</td>        <td   align="right" > {{sin}}</td>  <td  >  </td>   
    </tr>  
      {{/sin}}   
     {{#sout}} 
    <tr>
        <td  > {{#t}}common.sluzhbova_vydacha{{/t}}</td>        <td   align="right" > {{sout}}</td>  <td  >  </td>   
    </tr>  
      {{/sout}}                                                  
   <tr>
        <td  > <b>{{#t}}common.realizatsiia{{/t}}</b></td>        <td  >  </td>        <td  >  </td>  
    </tr>    
   <tr>
        <td  > {{#t}}common.chekiv{{/t}}</td>        <td style="width:100px" align="right"  > {{cnt}}</td>   <td  >  </td>   
    </tr>    
    <tr>
        <td  > {{#t}}common.oplaty{{/t}}</td>        <td  >  </td>    <td  >  </td>   
    </tr>  
     {{#pays}} 
    <tr>
        <td  >  {{label}}</td>        <td  align="right"  > {{sum}}</td>   <td  >  </td>   
    </tr>  
      {{/pays}}   
  <tr>
        <td  > <b>{{#t}}common.povernennia{{/t}}</b></td>        <td  >  </td>    <td  >  </td>   
    </tr>    
   <tr>
        <td  > {{#t}}common.chekiv{{/t}}</td>        <td  align="right" > {{rcnt}}</td>     <td  >  </td>   
    </tr>    
    <tr>
        <td  > {{#t}}common.oplaty{{/t}}</td>        <td  >  </td>     <td  >  </td>   
    </tr>  
     {{#rpays}} 
    <tr>
        <td  >  {{label}}</td>        <td   align="right" > {{sum}}</td>  <td  >  </td>   
    </tr>  
      {{/rpays}}   
 
     


</table>
<br>