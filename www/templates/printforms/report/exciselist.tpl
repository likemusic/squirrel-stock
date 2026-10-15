<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="5">
           {{#t}}common.aktsyzni_marky{{/t}}   
        </td>
    </tr>
    <tr>

        <td align="center" colspan="5">
            {{#t}}common.period_z_po_2{{/t}}
        </td>
    </tr>
     {{#itemname}}
     <tr>

        <td   colspan="5">
         {{#t}}print.exciselist.tovar{{/t}}
        </td>
    </tr>
     {{/itemname}}
      <tr>

        <td   colspan="5">
         {{#t}}print.exciselist.oborot{{/t}} <b> {{totamount}}</b>  {{#t}}print.exciselist.nbsp_aktsyz{{/t}} <b> {{amount}}</b>
        </td>
    </tr>    
    <tr style="font-weight: bolder;">

        <th style="border: solid black 1px">{{#t}}print.exciselist.marka{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.artykul{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.dokument{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.data{{/t}}</th>

       
    </tr>
    {{#_detail}}
    <tr>


        <td>{{stamp}}</td>
        <td>{{itemname}}</td>
        <td data-type="s">{{item_code}}</td>
        <td>{{document_number}}</td>
        <td>{{document_date}}</td>

     
 
    </tr>
    {{/_detail}}
  
</table>


