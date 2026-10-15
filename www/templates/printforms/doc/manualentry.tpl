<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="3" align="center">
            <b> {{#t}}print.manualentry.ruchna_provodka_vid{{/t}}</b> <br>
        </td>
    </tr>
    <tr>
        <td colspan="3"  >
             {{notes}}
        </td>
    </tr>    
  <tr style="font-weight: bolder;">
      
        <th    style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.debet{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kredyt{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right">{{#t}}common.suma{{/t}}</th>
    
     
    </tr>
    {{#_detail}}
    <tr>
 
        <td  >{{dt}}</td>
        <td  >{{ct}}</td>
     
        <td align="right"  >{{amount}}</td>
   
    
    </tr>
    {{/_detail}}


</table>


