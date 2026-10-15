<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="4" align="center">
            <b> {{#t}}print.eq.vid{{/t}}</b> <br>
        </td>
    </tr>
  
 
    <tr>
        <td colspan="2">
            <b>{{#t}}print.eq.naimenuvannia{{/t}}</b> {{eqname}}
        </td>
        <td colspan="2">
            <b>{{#t}}print.eq.inv_nomer{{/t}}</b> {{invnumber}}
        </td>
   </tr> 
 

   {{#isamount }}
    <tr>
        <td colspan="4">
          <b>{{#t}}common.suma_2{{/t}}</b>   {{amount }}
        </td>
    </tr>
   {{/isamount }}   
   {{#iscust }}
    <tr>
        <td colspan="4">
          <b>{{#t}}common.kontrahent_2{{/t}}</b>   {{customer_name }}  
        </td>
    </tr>
   {{/iscust }}   
 
    <tr>
        <td colspan="4">
          {{#ispa}}
          <b>{{#t}}print.eq.vyrobnycha_dilnytsia{{/t}}</b>   {{pa_name}}   &nbsp;&nbsp;&nbsp;&nbsp;
          {{/ispa}} 
           {{#isemp}}
            <b>{{#t}}common.vidpovidalnyi{{/t}}</b> {{emp_name}}
          {{/isemp}} 
        </td>
    </tr>
   {{#isitem }}  
    <tr>
        <td colspan="4">
          <b>{{#t}}print.eq.tmts{{/t}}</b>   {{item_name}}   <b>{{#t}}common.sklad{{/t}}</b> {{store_name}}
        </td>
 </tr>        
  {{/isitem }}     
    <tr>
        <td colspan="4">
            {{{notes}}}
        </td>
    </tr>
     

    <tr>    
        <td colspan="4" > 
        <br>    {{#t}}print.eq.pidpys{{/t}}
        </td>
        

    </tr>

</table>


