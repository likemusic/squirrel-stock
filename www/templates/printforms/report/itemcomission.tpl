<table class="ctable" border="0" cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="7">
            {{#t}}print.itemcomission.komisiini_tovary_na{{/t}}
        </td>
    </tr>
    
    {{#iscust}}
    <tr >
        <td  colspan="7">
          <b> {{#t}}print.itemcomission.komitent{{/t}}</b>  {{cust}}
            <br>
            <br>
        </td>
    </tr>
    {{/iscust}}
    
    <tr style="font-weight: bolder;">

         
        <th style="border: solid black 1px">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.tsina{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemcomission.prydbano{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemcomission.prodano{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.povernuto{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemcomission.nakladna{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

   
        <td>{{itemname}}</td>
        <td data-type="s">{{item_code}}</td>

        <td align="right">{{price}}</td>
        <td align="right">{{buyqty}}</td>
        <td align="right">{{sellqty}}</td>
        <td align="right">{{retqty}}</td>
        <td align="right">{{docs}}</td>

    </tr>
    {{/_detail}}
 
    <tr >
        <td  colspan="7">
          <b> {{#t}}print.itemcomission.prodazhi{{/t}}</b>  
            <br>
           
        </td>
    </tr>
   {{#_detail2}}
     <tr>
        <td  >{{#t}}print.itemcomission.data{{/t}}</td>
        <td colspan="6">{{#t}}print.itemcomission.dokumenty{{/t}}</td>
    </tr>
      {{#items}}
     <tr>
        <td > </td>
        <td > {{itemname}}</td>
        <td align="right"> {{sm}}</td>
        <td colspan="4"> </td>
    </tr>
     {{/items}}
     <tr>
        <td colspan="7">{{#t}}print.itemcomission.suma{{/t}}</td>
    </tr>

   {{/_detail2}}   
</table>
<br> <br>

