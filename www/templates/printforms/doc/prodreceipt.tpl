<table class="ctable" border="0" cellspacing="0" cellpadding="2">

    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="8" valign="middle">
            {{#t}}print.prodreceipt.oprybutkuvannia_z_vyrobnytstva_vid{{/t}} <br>
        </td>
    </tr>
    <tr>
        <td colspan="8" valign="middle">
            {{#t}}common.vyrobnycha_dilianka{{/t}} <b>{{pareaname}}</b><br>
        </td>
    </tr>
   <tr>
        <td colspan="8" valign="middle">
            {{#t}}common.na_sklad{{/t}} <b>{{storename}}</b><br>
        </td>
    </tr>
    {{#emp}}
    <tr>
        <td colspan="6">
            <b>{{#t}}print.prodmove.vykonavets{{/t}}</b> {{emp}}
        </td>
    </tr>
 
    {{/emp}}      
    <tr>
        <td colspan="8" valign="middle">
            {{{notes}}}<br>
        </td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>

        <th align="right" style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
        <th align="right" style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}</th>
        <th align="right" style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td colspan="2">{{itemname}}</td>
        <td colspan="2" data-type="s">{{itemcode}}</td>
        <td>{{msr}}</td>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="8" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>


</table>

