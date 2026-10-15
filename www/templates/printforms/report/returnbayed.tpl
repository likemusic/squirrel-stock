<table class="ctable" border="0" cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="3">
            {{#t}}print.returnbayed.povernennia_postachalnykam{{/t}}
        </td>
    </tr>

    <tr style="font-weight: bolder;">

         
        <th style="border: solid black 1px">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.itemcomission.prydbano{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.povernuto{{/t}}</th>
        <th align="right" style="border: solid black 1px">%</th>

    </tr>
    {{#_detail}}
    <tr>

   
        <td>{{itemname}}</td>
        <td data-type="s">{{item_code}}</td>

        <td align="right">{{buyqty}}</td>
        <td align="right">{{rqty}}</td>
        <td align="right">{{pr}}</td>

    </tr>
    {{/_detail}}


</table>
<br> <br>

