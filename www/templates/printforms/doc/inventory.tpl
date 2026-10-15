<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="5" align="center">
            <b> {{#t}}print.inventory.inventaryzatsiia_vid{{/t}}</b> <br>
        </td>
    </tr>
   <tr>
        <td colspan="5">{{{notes}}}</td>
    </tr>
    
    <tr>
        <td colspan="5">
            <b> {{#t}}print.inventory.sklad{{/t}}</b> {{store}}
        </td>

    </tr>
    {{#storeemp}}
    <tr>
        <td colspan="4">
            <b>{{#t}}common.spivrobitnyk_2{{/t}}</b> {{storeemp}}
        </td>
    </tr>
 
    {{/storeemp}}     
    {{#reserved}}
  <tr>
        <td colspan="5">
            <small>{{#t}}print.inventory.vrakhovuiuchy_zarezervovani{{/t}}</small>
        </td>

    </tr>
    {{/reserved}}


    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.nazva{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.artykul{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"></th>


        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kil{{/t}}</th>
        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}app.main.fakt{{/t}}</th>
    </tr>
       {{#_detaillost}}
    <tr>

        <td>{{item_name}}</td>
        <td data-type="s">{{item_code}}</td>
        <td data-type="s">{{snumber}}</td>


        <td align="right">{{quantity}}</td>
        <td align="right" style="color:red">{{qfact}}</td>
    </tr>
    {{/_detaillost}}
    {{#_detailover}}
    <tr>

        <td>{{item_name}}</td>
        <td data-type="s">{{item_code}}</td>
        <td data-type="s">{{snumber}}</td>


        <td align="right">{{quantity}}</td>
        <td align="right"  >{{qfact}}</td>
    </tr>
    {{/_detailover}}    
    {{#_detail}}
    <tr>

        <td  >{{item_name}}</td>
        <td data-type="s">{{item_code}}</td>
        <td data-type="s">{{snumber}}</td>


        <td align="right">{{quantity}}</td>
        <td align="right">{{qfact}}</td>
    </tr>
    {{/_detail}}
 
       {{#summinus}}  
   <tr>
        <td  >
            <b> {{#t}}print.inventory.nedostacha_na_sumu{{/t}}   </b>
        </td>
      <td colspan="4" align="right" style="color:red">
            <b>  {{summinus}}   </b>
        </td>

    </tr>  
     {{/summinus}}      
   {{#sumplus}} 
   <tr>
        <td  >
           <b>  {{#t}}print.inventory.nadlyshky_na_sumu{{/t}}  </b>
        </td> 
           <td colspan="4" align="right">
             <b>  {{sumplus}}   </b>
        </td>

    </tr>
       {{/sumplus}} 
     
    
</table>


