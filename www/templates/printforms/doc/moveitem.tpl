<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="4" align="center">
            <b>{{#t}}print.moveitem.peremishchennia_tmts_vid{{/t}}</b> <br>
        </td>
    </tr>
    <tr>
        <td colspan="4">
            <b> {{#t}}common.zi_skladu_2{{/t}}</b> {{#t}}print.moveitem.na{{/t}}
        </td>

    </tr>
    {{#storeemp}}
    <tr>
        <td colspan="4">
            <b>{{#t}}print.moveitem.zi_spivrobitnyka{{/t}}</b> {{storeemp}}
        </td>
    </tr>
 
    {{/storeemp}}  
   {{#tostoreemp}}
    <tr>
        <td colspan="4">
            <b>{{#t}}common.na_spivrobitnyka_2{{/t}}</b> {{tostoreemp}}
        </td>
    </tr>
 
    {{/tostoreemp}}  
     <tr>
        <td colspan="4">{{{notes}}}</td>
    </tr>

    <tr style="font-weight: bolder;">

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.nazva{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.artykul{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"></th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>


        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kil{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

        <td>{{item_name}}</td>
        <td data-type="s">{{item_code}}</td>

        <td align="right">{{snumber}}</td>
        <td>{{msr}}</td>
        <td align="right">{{quantity}}</td>

    </tr>
    {{/_detail}}
   
</table>



