<table class="ctable" border="0"   cellpadding="2" cellspacing="0">


    <tr>

        <td align="center" colspan="4">
            {{#t}}common.period_z_po_2{{/t}} <br> <br>
        </td>
    </tr>
    {{#_type1}}
    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="4">
            {{#sqs_charity}}{{#t}}sqs.charity.income_by_items{{/t}}{{/sqs_charity}}{{^sqs_charity}}{{#t}}print.income.zakupivli_po_tovaram{{/t}}{{/sqs_charity}} <br><br>
        </td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>

        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.na_sumu{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>


        <td>{{name}}</td>
        <td>{{code}}</td>

        <td align="right">{{qty}}</td>
        <td align="right">{{summa}}</td>

    </tr>
    {{/_detail}}
    <tr>  <td> </td>
        <td> </td>

        <td  > </td>
        <td align="right"><b>{{total}}</b></td>    </tr>   
</table>
{{/_type1}}
{{#_type2}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="4">
        {{#sqs_charity}}{{#t}}sqs.charity.income_by_suppliers{{/t}}{{/sqs_charity}}{{^sqs_charity}}{{#t}}print.income.zakupivli_po_postachalnykam{{/t}}{{/sqs_charity}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
    <th colspan="2" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
    <th></th>
</tr>
{{#_detail}}
<tr>


    <td colspan="2">{{name}}</td>


    <td align="right">{{summa}}</td>
    <td></td>
</tr>
{{/_detail}}
<tr>  <td colspan="2"> </td>
    <td align="right"><b>{{total}}</b></td>    
    <td> </td>     </tr>
</table>
{{/_type2}}
{{#_type3}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="4">
        {{#sqs_charity}}{{#t}}sqs.charity.income_by_dates{{/t}}{{/sqs_charity}}{{^sqs_charity}}{{#t}}print.income.zakupivli_po_datam{{/t}}{{/sqs_charity}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
    <th style="border: solid black 1px;width:120px;">{{#t}}common.data{{/t}}</th>

    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
    <th></th>
    <th></th>

</tr>
{{#_detail}}
<tr>


    <td>{{dt}}</td>


    <td align="right">{{summa}}</td>
    <td></td>
    <td></td>

</tr>
{{/_detail}}
<tr>  <td  > </td>
    <td align="right"><b>{{total}}</b></td>    
    <td> </td>    <td> </td>     </tr>
</table>
{{/_type3}}
{{#_type4}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="5">
         {{#t}}print.income.posluhy_ta_roboty{{/t}} <br><br>
    </td>
</tr>
<tr style="font-weight: bolder;">
    <th colspan="2" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

    
    <th align="right" style="border: solid black 1px; ">{{#t}}common.kil{{/t}}</th>
    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
    
 
</tr>
{{#_detail}}
<tr>


    <td colspan="2">{{name}}</td>


    <td align="right">{{qty}}</td>
    <td align="right">{{summa}}</td>
 
</tr>
{{/_detail}}

<tr><td colspan="3" ></td> <td align="right" ><b>{{total}}</b></td>  </tr>
</table>

{{/_type4}}
{{#_type5}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="5">
        {{#sqs_charity}}{{#t}}sqs.charity.income_by_categories{{/t}}{{/sqs_charity}}{{^sqs_charity}}{{#t}}print.income.zakupivli_po_katehoriiam{{/t}}{{/sqs_charity}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
    <th colspan="3" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
 

 
</tr>
{{#_detail}}
<tr>


    <td colspan="3">{{name}}</td>


    <td align="right">{{summa}}</td>
 
  
 
</tr>
{{/_detail}}
<tr><td colspan="3" ></td> <td align="right" ><b>{{total}}</b></td> 
 


</tr>

</table>
{{/_type5}}   
