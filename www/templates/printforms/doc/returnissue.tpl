<table class="ctable" border="0" cellspacing="0" cellpadding="2">


    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="7" valign="middle">
            <br><br> {{#t}}print.returnissue.povernennia_vid{{/t}} <br><br><br>
        </td>
    </tr>
    {{#fiscalnumber}}    
    <tr>
        <td></td>
        <td>{{#t}}app.poslist.fiskalnyi_nomer{{/t}}</td>
        <td colspan="5" data-type="s">{{fiscalnumber}}</td>
    </tr>
    {{/fiscalnumber}}    
   <tr>
        <td></td>
        <td>{{#t}}common.pokupets{{/t}}</td>
        <td colspan="5">{{customer_name}}</td>
    </tr>
   <tr>
        <td colspan="7">{{{notes}}}</td>
    </tr>
    
    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}
        </th>

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}  {{#nds}} {{#t}}common.bez_pdv{{/t}}  {{/nds}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td colspan="2">{{tovar_name}}</td>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}  {{#nds}} ({{pricenonds}})  {{/nds}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
       {{#nds}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.v_t_ch_pdv{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{nds}}</td>
    </tr>
       {{/nds}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{payamount}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="5" align="right">{{#t}}print.returnissue.oplacheno{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{payed}}</td>
    </tr>


</table>

