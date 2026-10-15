<table class="ctable" border="0"   cellpadding="2" cellspacing="0">


    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="4">
            {{#t}}print.reserved.zarezervovani_tovary_na{{/t}}<br>
        </td>
    </tr>
   
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.sklad{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.dokument{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.pokupets{{/t}}</th>

        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

        <td>{{itemname}}</td>
        <td data-type="s">{{item_code}}</td>

        <td>{{store}}</td>
        <td>{{document_number}}</td>
        <td>{{customer}}</td>

        <td align="right">{{qty}}</td>

    </tr>
    {{/_detail}}
    </table>
