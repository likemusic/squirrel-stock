<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="5">
            {{#t}}print.endday.kinets_dnia{{/t}}
        </td>
    </tr>
   <tr style="  font-weight: bolder;">
        <td   colspan="5">
            {{#t}}print.endday.po_korystuvachakh{{/t}}
        </td>
    </tr>
 
    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px"> {{#t}}common.imia{{/t}}</th>

        <th colspan="2" style="border: solid black 1px">{{#t}}common.rakhunok{{/t}}</th>
        <th  align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
        <th  align="right" style="border: solid black 1px">{{#t}}common.vydatok{{/t}}</th>
        


    </tr>
    {{#_detail}}
    <tr>


        <td>{{username}}</td>
        <td colspan="2">{{mf_name}}</td>
        <td  align="right" >{{dt}}</td>
        <td  align="right" >{{ct}}</td>
         
       


    </tr>
    {{/_detail}}
    
    {{#showmf}}
    <tr style="  font-weight: bolder;">
        <td   colspan="5">
            {{#t}}print.endday.po_hroshovykh_rakhunkakh{{/t}}
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th   style="border: solid black 1px"> {{#t}}common.rakhunok{{/t}}

        <th align="right"  style="border: solid black 1px">{{#t}}common.pochatok{{/t}}
        <th align="right"  style="border: solid black 1px">{{#t}}common.prybutok{{/t}}
        <th align="right"  style="border: solid black 1px">{{#t}}common.vydatok{{/t}}
        <th align="right" style="border: solid black 1px">{{#t}}print.endday.kinets{{/t}}
     
        


    </tr>
    {{#_detail2}}
    <tr>


        <td  >{{mf_name}}</td>
        <td align="right" >{{b}}</td>
        <td align="right" >{{dt}}</td>
        <td align="right" >{{ct}}</td>
        <td align="right" >{{e}}</td>

     
   


    </tr>
    {{/_detail2}}  
  {{/showmf}}
</table>
<br> 

