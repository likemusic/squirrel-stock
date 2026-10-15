<table class="ctable" border="0"    >


    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="5">
            {{#t}}print.compareact.akt_zvirky_vid{{/t}}<br>
        </td>
    </tr>
   <tr style=" font-weight: bolder;">
        <td  colspan="5">
            {{#t}}print.compareact.kontrahent{{/t}}<br><br>
        </td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px">{{#t}}common.data{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.nomer{{/t}}</th>

        <th align="right" style="border: solid black 1px">{{#t}}common.debet{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.kredyt{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.compareact.saldo{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}print.compareact.typ_dokumentu{{/t}}</th>
        <th  style="border: solid black 1px">{{#t}}print.compareact.oplaty{{/t}}</th>
        <th  style="border: solid black 1px">{{#t}}common.prymitka{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

        <td>{{document_date}}</td>

        <td> {{document_number}}</td>

        <td align="right">{{active}}</td>
        <td align="right">{{passive}}</td>
        <td align="right">{{bal}}</td>
        <td style="font-size:smaller;">{{meta_desc}}</td>
        <td style="font-size:smaller;" >{{pays}}</td>
        <td style="font-size:smaller;">{{notes}}</td>

    </tr>
    {{/_detail}}
    
   </table> 
