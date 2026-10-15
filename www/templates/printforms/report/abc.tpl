<table class="ctable" cellspacing="0" cellpadding="1">
    <tr   style="font-weight: bolder;">
        <td colspan="4">
            <h3 style="font-size: 16px;">{{#t}}print.abc.abc_analiz_z_po{{/t}}</h3>
        </td>
    </tr>
    <tr style="font-weight: bolder;">

        <th   style="border-bottom:1px #000 solid;">{{#t}}common.nazva{{/t}}</th>
        <th   style="border-bottom:1px #000 solid;" align="right">{{#t}}print.abc.znach{{/t}} </th>
        <th   style="border-bottom:1px #000 solid;" align="right">%</th>
        <th   style="border-bottom:1px #000 solid;"></th>
    </tr>
    {{#_detail}}
    <tr>

        <td    >{{name}}</td>
        <td   class="{{color}}" align="right">{{value}} </td>
        <td   class="{{color}}" align="right">{{perc}} </td>
        <td   class="{{color}}">{{group}}</td>

    </tr>
    {{/_detail}}
    <tr>

        <td   align="right"><b>{{#t}}print.abc.vsoho_a{{/t}}</b></td>
        <td   align="right"><b>{{totala}}</b> </td>
        <td   align="right"> </td>
        <td  > </td>

    </tr>
    <tr>

        <td   align="right"><b>{{#t}}print.abc.vsoho_b{{/t}}</b></td>
        <td   align="right"><b>{{totalb}}</b> </td>
        <td   align="right"> </td>
        <td  ></td>

    </tr>
    <tr>

        <td   align="right"><b>{{#t}}print.abc.vsoho_c{{/t}}</b></td>
        <td   align="right"><b>{{totalc}}</b> </td>
        <td   align="right"> &nbsp;</td>
        <td  ></td>

    </tr>
    <tr>

        <td   align="right"><b>{{#t}}common.vsoho{{/t}}</b></td>
        <td   align="right"><b>{{total}}</b> </td>
        <td   align="right"> &nbsp;</td>
        <td  ></td>

    </tr>

</table>


