<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="7">
           {{#t}}print.obsaldo.oborotno_saldova_vidomist{{/t}}
        </td>
    </tr>
    <tr>

        <td align="center" colspan="7">
            {{#t}}common.period_z_po_2{{/t}}
        </td>
    </tr>
 

    <tr  >
 
        <th rowspan="2" style="border: solid black 1px">{{#t}}common.rakhunok{{/t}}</th>

      
        <th colspan="2" style="border: solid black 1px">{{#t}}print.accountactivity.poch_saldo{{/t}}</th>
        <th colspan="2" style="border: solid black 1px">{{#t}}print.accountactivity.oborot{{/t}}</th>
        <th colspan="2" style="border: solid black 1px">{{#t}}print.accountactivity.kin_saldo{{/t}}</th>
        
    </tr>
    <tr  >
 
        <th   style="border: solid black 1px">{{#t}}common.debet{{/t}}</th>
        <th   style="border: solid black 1px">{{#t}}common.kredyt{{/t}}</th>
        <th   style="border: solid black 1px">{{#t}}common.debet{{/t}}</th>
        <th   style="border: solid black 1px">{{#t}}common.kredyt{{/t}}</th>
        <th   style="border: solid black 1px">{{#t}}common.debet{{/t}}</th>
        <th   style="border: solid black 1px">{{#t}}common.kredyt{{/t}}</th>

      
    
    </tr>
    {{#_detail}}
    <tr>

        <td>{{acc}}</td>

        <td align="right">{{startdt}}</td>
        <td align="right">{{startct}}</td>

        <td align="right">{{amountdt}}</td>
        <td align="right">{{amountct}}</td>
        <td align="right">{{enddt}}</td>
        <td align="right">{{endct}}</td>
       
 
    </tr>
    {{/_detail}}
 
    <tr style="font-weight:bolder">

        <td> </td>

        <td align="right">{{bdt}}</td>
        <td align="right">{{bct}}</td>

        <td align="right"></td>
        <td align="right"></td>
        <td align="right">{{edt}}</td>
        <td align="right">{{ect}}</td>
       
 
    </tr>

 
  
   
  
</table>


