<table class="ctable" border="0"    >

    <tr>
        <td align="center" colspan="3">
            <h4> {{#t}}print.outfood.zvit_po_kafe{{/t}} </h4>
        </td>
    </tr>
    <tr>

        <td colspan="3">
            {{#t}}common.period_z{{/t}} <b>{{datefrom}}</b> {{#t}}common.po{{/t}} <b>{{dateto}}</b> <br>
        </td>
    </tr>
    {{#isd1}}
 
    <tr>

        <td colspan="3">
            <h5> {{#t}}common.zamovlennia{{/t}} </h5>
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.data{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}print.outfood.den{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.na_sumu{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.outfood.ser_chek{{/t}}</th>


        </tr>

        {{#_detail}}
    <tr>


        <td>{{dt}}</td>

        <td  >{{day}}</td>
        <td align="right">{{qty}}</td>
        <td align="right">{{amount}}</td>
        <td align="right">{{av}}</td>

       
       

    </tr>
    {{/_detail}}

    {{/isd1}}
 
   {{#isd2}}
    <tr>

        <td colspan="3"><br>
            <h5>{{#t}}print.outfood.pozytsii{{/t}}   </h5>
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.na_sumu{{/t}}</th>
       </tr>
        
        {{#_detail2}}
    <tr>


        <td>{{name}}</td>

        <td align="right" >{{qty}}</td>
        <td align="right">{{sum}}</td>

        
    </tr>
    {{/_detail2}}
 
   {{/isd2}}   
 

</table>


