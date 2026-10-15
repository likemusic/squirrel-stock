<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="4" align="center">
            <b> {{#t}}print.transitem.perekomplektatsii_tmts_vid{{/t}}</b> <br>
        </td>
    </tr>
    <tr>
        <td colspan="4">
            <b> {{#t}}common.zi_skladu_2{{/t}}</b>  {{from}}
        </td>
     

    </tr>
{{#fromlist}}
  <tr>
        <td>
           {{fromname}} 
        </td>
        <td>
          {{fromcode}}     
        </td>
       <td>
          {{fromqty}}      
        </td>
        <td>
          {{fromprice}}      
        </td>

    </tr> 
    {{/fromlist}}   

    <tr>
        <td colspan="4">
            <b> {{#t}}common.na_sklad_2{{/t}}</b>   {{to}}
        </td>
     

    </tr>    
 {{#tolist}}
  <tr>
        <td>
           {{toname}} 
        </td>
        <td>
          {{tocode}}     
        </td>
       <td>
          {{toqty}}      
        </td>
        <td>
          {{toprice}}      
        </td>

    </tr> 
    {{/tolist}}   

  
    <tr>
        <td colspan="4">{{{notes}}}</td>
    </tr>


</table>


