<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="8">
            {{#t}}common.nelikvidni_tovary{{/t}}
        </td>
    </tr>
 
    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px"> {{#t}}common.naimenuvannia{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.katehoriia{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.brend{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.sklad{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.komirka{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.tsina{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.na_skladi{{/t}}</th>


    </tr>
    {{#_detail}}
    <tr>


        <td>{{itemname}}</td>
        <td data-type="s">{{item_code}}</td>
        <td>{{cat_name}}</td>
        <td>{{brand}}</td>
        <td>{{store}}</td>
        <td>{{cell}}</td>

        <td align="right">{{price}}</td>
        <td align="right">{{qty}}</td>


    </tr>
    {{/_detail}}


  

</table>
<br>  

