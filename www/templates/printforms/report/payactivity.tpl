<table border="0" class="ctable" cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="6">
            {{#t}}print.payactivity.rukh_po_hroshovykh_rakhunkakh{{/t}}
        </td>
    </tr>
    <tr>

        <td align="center" colspan="6">
            {{#t}}print.payactivity.period_z_po_nbsp_nbsp{{/t}} <strong>{{mf_name}}</strong>
        </td>
    </tr>

    <tr style="font-weight: bolder;">

        <th style="border: solid black 1px">{{#t}}common.data{{/t}}</th>


        <th align="right" style="border: solid black 1px">{{#t}}print.itemactivity.poch{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}print.itemactivity.prykh{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemactivity.vytr{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemactivity.kin{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

        <td>{{date}}</td>


        <td align="right">{{in}}</td>
        <td align="right">{{obin}}</td>
        <td align="right">{{obout}}</td>
        <td align="right">{{out}}</td>

    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">

        <td align="right">{{#t}}common.razom{{/t}}</td>


        <td align="right"></td>
        <td align="right">{{tin}}</td>
        <td align="right">{{tout}}</td>
        <td align="right"></td>
        <td></td>
    </tr>

</table>


