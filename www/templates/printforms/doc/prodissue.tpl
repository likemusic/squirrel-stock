<table class="ctable" border="0" cellspacing="0" cellpadding="2">


    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="9" valign="middle">
            {{#t}}print.prodissue.spysannia_na_vyrobnytstvo_vid{{/t}} <br>
        </td>
    </tr>
    <tr>
        <td colspan="9" valign="middle">
            {{#t}}common.vyrobnycha_dilianka{{/t}} <b>{{pareaname}}</b><br>
        </td>
    </tr>
   <tr>
        <td colspan="9" valign="middle">
            {{#t}}common.zi_skladu{{/t}} <b>{{storename}}</b><br>
        </td>
    </tr>
    {{#emp}}
    <tr>
        <td colspan="9">
            <b>{{#t}}print.prodissue.vidpovidalnyi{{/t}}</b> {{emp}}
        </td>
    </tr>
 
    {{/emp}}       
    <tr>
        <td colspan="9" valign="middle">
            {{{notes}}}<br>
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.naimenuvannia{{/t}}
        </th>
        <th colspan="2" style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.od{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}doc.prodissue.kom{{/t}}</th>

        <th align="right" style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td colspan="2">{{tovar_name}}</td>
        <td colspan="2" data-type="s">{{tovar_code}}</td>
        <td>{{msr}}</td>
        <td>{{cell}}</td>

        <td align="right">{{quantity}}</td>

    </tr>
    {{/_detail}}


</table>

