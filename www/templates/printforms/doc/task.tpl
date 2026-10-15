<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="7" align="center">
            {{#t}}print.task.nariad_vid{{/t}}
        </td>
    </tr>
    {{#pareaname}}
    <tr>
        <td colspan="7">
            {{#t}}print.task.vyrob_dilianka{{/t}}
        </td>

    </tr>
    {{/pareaname}}

    {{#baseddoc}}
    <tr>
        <td colspan="7">
            {{#t}}print.task.zamovlennia{{/t}}
        </td>

    </tr>
    {{/baseddoc}}

    {{#cust}}
    <tr>
        <td colspan="7">
            {{#t}}print.task.zamovnyk{{/t}}
        </td>

    </tr>
    {{/cust}}

    <tr style="font-weight: bolder;">

        <th colspan="7" style="text-align: left;">{{#t}}common.roboty{{/t}}</th>

    </tr>
    <tr style="font-weight: bolder;">
        <th   style="border: 1px solid black;">№</th>
        <th style="border: 1px solid black;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: 1px solid black;">{{#t}}common.katehoriia{{/t}}</th>
        <th style="border: 1px solid black;"   align="right">{{#t}}print.task.kil{{/t}}</th>
        <th style="border: 1px solid black;"   align="right">{{#t}}common.suma{{/t}}</th>
        <th style="border: 1px solid black;"   align="right">{{#t}}print.task.hodyn{{/t}}</th>
        <th style="border: 1px solid black;"   > </th>


    </tr>
    {{#_detail}}
    <tr>
        <td>{{no}}</td>
        <td>{{service_name}}</td>
        <td>{{category}}</td>
        <td align="right">{{quantity}}</td>
        <td align="right">{{cost}}</td>
        <td align="right">{{hours}}</td>
        <td  >{{desc}}</td>


    </tr>
    {{/_detail}}

    <tr style="font-weight: bolder;">

        <th colspan="7" style="text-align: left;">{{#t}}common.hotova_produktsiia{{/t}}</th>

    </tr>
    <tr style="font-weight: bolder;">
        <th   style="border: 1px solid black;">№</th>
        <th style="border: 1px solid black;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: 1px solid black;">{{#t}}common.kod{{/t}}</th>
        <th style="border: 1px solid black;"   align="right">{{#t}}common.kil{{/t}}</th>
        <th style="border: 1px solid black;"   align="right"> </th>
        <th style="border: 1px solid black;"   align="right"> </th>
        <th style="border: 1px solid black;"    > </th>


    </tr>
    {{#_detailprod}}
    <tr>
        <td>{{no}}</td>
        <td>{{itemname}}</td>
        <td>{{item_code}}</td>
        <td align="right">{{quantity}}</td>
        <td align="right"> </td>
        <td align="right"> </td>
        <td  >{{desc}}</td>


    </tr>
    {{/_detailprod}}

    <tr style="font-weight: bolder;">

        <th colspan="7" style="text-align: left;">{{#t}}common.vykonavtsi{{/t}}</th>

    </tr>
    {{#_detail3}}
    <tr>

        <td colspan="5">{{emp_name}}</td>
       <td colspan="2">{{emp_ktu}}</td>


    </tr>
    {{/_detail3}}


    {{#iseq}}

    <tr style="font-weight: bolder;">

        <th colspan="7" style="text-align: left;">{{#t}}doc.task.ustatkuvannia{{/t}}</th>

    </tr>
    {{#_detail2}}
    <tr>

        <td colspan="5">{{eq_name}}</td>

        <td colspan="2">{{invnumber}}</td>

    </tr>
    {{/_detail2}}
    {{/iseq}}
    <tr>
        <td colspan="7">
            {{{notes}}}
        </td>

    </tr>

</table>


