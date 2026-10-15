<table class="ctable"    >

    <tr>
        <td align="center" colspan="5">
            <h4> {{#t}}print.prrodoclist.fiskalizovani_cheky{{/t}} </h4>
        </td>
    </tr>
    <tr>

        <td colspan="5">
            {{#t}}common.period_z{{/t}} <b>{{datefrom}}</b> {{#t}}common.po{{/t}} <b>{{dateto}}</b> <br>
        </td>
    </tr>
   

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.data{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.nomer{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}print.prrodoclist.fn{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.typ{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.suma{{/t}}</th>
   </tr>
        {{#detail}}
    <tr>


        <td>{{docdata}}</td>

        <td  >{{docnumber}}</td>
        <td  >{{fn}}</td>
        <td  >{{type}}</td>
        <td align="right">{{amount}}</td>

       
       

    </tr>
    {{/detail}}

     <tr  >


        <td colspan="5" style="border: solid black 1px">
           {{#t}}common.chekiv{{/t}} <b>{{cnt}}</b> {{#t}}print.prrodoclist.na_sumu{{/t}} <b>{{tam}}</b>{{#t}}print.prrodoclist.povernen{{/t}}  <b>{{rcnt}}</b> {{#t}}print.prrodoclist.na_sumu{{/t}} <b>{{rtam}}</b>. 
        </td>
      
       
   </tr> 
 

</table>


