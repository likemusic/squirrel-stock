<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="8">
            {{#t}}print.itemactivity.rukh_po_skladu{{/t}}
        </td>
       {{#docs}}  <td> </td>       {{/docs}} 
    </tr>
    <tr>

        <td align="center" colspan="8">
            {{#t}}common.period_z_po_2{{/t}}
        </td>
             {{#docs}}  <td> </td>       {{/docs}} 
 
    </tr>
 

    <tr style="font-weight: bolder;">

        <th style="border: solid black 1px">{{#t}}common.data{{/t}}</th>

         <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>
         <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
 
          {{#docs}}  <th style="border: solid black 1px">{{#t}}common.dokument{{/t}}</th>     {{/docs}} 
               
       
        <th align="right" style="border: solid black 1px">{{#t}}print.itemactivity.poch{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}print.itemactivity.prykh{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemactivity.vytr{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemactivity.kin{{/t}}</th>
       
    </tr>
    {{#_detail}}
    <tr   > 

        <td>{{date}}</td>

      
        <td>{{name}}</td>
        <td>{{code}}</td>
          {{#docs}}  <td> {{docnumber}}</td>       {{/docs}} 
 
        <td align="right"  {{#itemrow}} style="font-weight:bolder"   {{/itemrow}} >{{in}}</td>
        <td align="right"  {{#itemrow}} style="font-weight:bolder"   {{/itemrow}} >{{obin}}</td>
        <td align="right"  {{#itemrow}} style="font-weight:bolder"   {{/itemrow}} >{{obout}}</td>
        <td align="right"  {{#itemrow}} style="font-weight:bolder"   {{/itemrow}} >{{out}}</td>
 
    </tr>
    {{/_detail}}
     {{#showqty}} 
    <tr>

        <td></td>
                 {{#docs}}  <td> </td>       {{/docs}} 
 
        <td></td>
        <td align="right"><b>{{#t}}common.vsoho_3{{/t}}</b></td>

        <td align="right"> </td>
        <td align="right"><b>{{bqin}}</b></td>
        <td align="right"><b>{{bqout}}</b></td>
        <td align="right"> </td>
    
    </tr>
   {{/showqty}} 
   
   {{^noshowpartion}} 
    <tr>

        <td></td>
                 {{#docs}}  <td> </td>       {{/docs}} 
 
        <td></td>
        <td align="right"><b>{{#t}}common.na_sumu{{/t}}</b></td>

        <td align="right"><b> </b></td>
        <td align="right"><b>{{bain}}</b></td>
        <td align="right"><b>{{baout}}</b></td>
        <td align="right"><b> </b></td>
    
    </tr>
   {{/noshowpartion}}    
</table>


