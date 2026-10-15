<table class="ctable" border="0" cellspacing="0" cellpadding="2">


    <tr style="font-weight: bolder;">
        <td></td>
        <td colspan="8">
            {{#t}}print.warranty.harantiinyi_talon_nbsp_vid{{/t}}
        </td>
    </tr>
    {{#basedon}}
   <tr>
        <td colspan="8">{{#t}}print.warranty.pidstava{{/t}}</td>
    </tr>
    {{/basedon}}    
  <tr>
        <td colspan="8">{{{notes}}}</td>
    </tr>
    
    <tr>
        <td colspan="8">&nbsp;</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th colspan="2"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right" >{{#t}}common.kil{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right" >{{#t}}common.tsina{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" align="right" >{{#t}}common.suma{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}print.warranty.ser_nomer{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}print.warranty.har_mis{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{ no}}</td>
        <td colspan="2">{{tovar_name}}</td>
        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
        <td align="right" data-type="s">{{sn}}</td>
        <td align="right">{{warranty}}</td>
    </tr>
    {{/_detail}}

    <tr>
        <td colspan="8">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="8">&nbsp;</td>
    </tr>
    <tr>
        <td></td>
        <td colspan="3">
            <b> {{#t}}common.pokupets{{/t}}</b> {{customer_name}}
        </td>
        <td colspan="4">
            <b> {{#t}}common.prodavets{{/t}}</b> {{firm_name}}
        </td>

    </tr>
    <tr>
        <td></td>
        <td colspan="8">
            {{#t}}print.warranty.pidpysy{{/t}}
        </td>


    </tr>
    <tr>
        <td></td>
        <td colspan="4">

        </td>
        <td colspan="3">
            {{#t}}print.warranty.m_p{{/t}}
        </td>


    </tr>
</table>

