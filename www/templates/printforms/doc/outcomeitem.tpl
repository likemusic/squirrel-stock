<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="6" align="center">
            <b>{{#t}}print.outcomeitem.spysannia_tmts_vid{{/t}}</b> <br>
        </td>
    </tr>
    <tr>
        <td colspan="6">
            <b> {{#t}}common.zi_skladu_2{{/t}}</b> {{from}}
        </td>

    </tr>
   {{#storeemp}}
    <tr>
        <td colspan="6">
            <b>{{#t}}print.moveitem.zi_spivrobitnyka{{/t}}</b> {{storeemp}}
        </td>
    </tr>
 
    {{/storeemp}}     
   {{#customer}}
    <tr>
        <td colspan="6">
            <b>{{#t}}print.incomeitem.partner{{/t}}</b> {{customer}}
        </td>
    </tr>
 
    {{/customer}}    
       



    <tr style="font-weight: bolder;">

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.nazva{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"></th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>


        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kil{{/t}}</th>
        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.na_sumu{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

        <td>{{item_name}}</td>
        <td data-type="s">{{item_code}}</td>

        <td align="right" data-type="s">{{snumber}}</td>
        <td>{{msr}}</td>
        <td align="right">{{quantity}}</td>
        <td align="right">{{sum}}</td>

    </tr>
    {{/_detail}}
    <tr>
        <td align="right" colspan="5"><b>{{#t}}common.vsoho{{/t}}</b></td>
          <td align="right">{{amount}}</td>
    </tr>   
    
    
    <tr>
        <td colspan="6">{{{notes}}}</td>
    </tr>    
</table>



