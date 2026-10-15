<table class="ctable"  >


    <tr>
        <td></td>
        <td>{{#t}}common.postachalnyk{{/t}}</td>
        <td colspan="9">{{customer_name}}</td>
    </tr>
      <tr>
        <td colspan="11">{{{notes}}}</td>
    </tr>

    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="11" valign="middle">
            <br> {{#t}}print.ordercust.zaiavka_vid{{/t}} <br><br>
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"  >№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.artykul{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}print.ordercust.kod_u_post{{/t}}</th>

        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.shtrykh_kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.brend{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;"> </th>

        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.kil{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.tsina{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;"  >{{#t}}common.suma{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td>{{itemname}}</td>
        <td data-type="s">{{itemcode}}</td>
        <td data-type="s">{{custcode}}</td>
      
        <td data-type="s">{{barcode}}</td>
        <td>{{brand}}</td>
        <td>{{msr}}</td>
        <td valign="top">{{desc}}</td>
        
        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="10" align="right">{{#t}}common.razom{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>


</table>

