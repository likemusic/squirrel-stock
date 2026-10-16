<table class="ctable"  >
    <tr>
        <td colspan="6">
            {{#t}}print.incomeservice.vykonavets{{/t}}
        </td>


    </tr>
    {{#isfirm}}
    <tr>
        <td colspan="6">
          {{#t}}print.incomeservice.zamovnyk{{/t}}
        </td>

    </tr>
    {{/isfirm}}
    {{#iscontract}}
    <tr>
        <td colspan="6">
            {{#t}}print.incomeservice.dohovir_vid{{/t}}
        </td>

    </tr>
    {{/iscontract}}

 
   <tr>
        <td colspan="6">{{notes}}</td>
    </tr>
    
    <tr style="font-weight: bolder;">
        <td colspan="6" align="center">
            {{#t}}print.incomeservice.otrymani_posluhy_vid{{/t}}
        </td>
    </tr>

</table>
<br>
<table class="ctable"   cellspacing="0" cellpadding="1" border="0">
    <tr style="font-weight: bolder;">
        <th   style="border: 1px solid black;">№</th>
        <th style="border: 1px solid black;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: 1px solid black;">{{#t}}common.opys{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.kil{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.vartist{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.suma{{/t}}</th>
 
         
     
    </tr>
    {{#_detail}}
    <tr>
        <td valign="top">{{no}}</td>
        <td valign="top">{{service_name}}</td>

        <td  valign="top">{{desc}}</td>

        <td valign="top" align="right">{{qty}}</td>
        <td valign="top" align="right">{{price}}</td>
        <td valign="top" align="right">{{amount}}</td>
 

    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td colspan="5" style="border-top: 1px solid black;" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td style="border-top: 1px solid black;" align="right">{{total}}</td>
    </tr>
   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="5" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="5" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  
    <tr>
        <td colspan="6"><br>
              {{notes}}
        </td>
    </tr>
   {{#hasitems}}
    <tr style="font-weight: bolder;">
        <th   style="border: 1px solid black;">№</th>
        <th style="border: 1px solid black;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: 1px solid black;">{{#t}}common.kod{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.kil{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.tsina{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.suma{{/t}}</th>
 
         
     
    </tr>
    {{#_detail2}}
    <tr>
        <td valign="top">{{no}}</td>
        <td valign="top">{{itemname}}</td>

        <td  valign="top">{{item_code}}</td>

        <td valign="top" align="right">{{qty}}</td>
        <td valign="top" align="right">{{price}}</td>
        <td valign="top" align="right">{{amount}}</td>
 

    </tr>
    {{/_detail2}}   
   
    <tr style="font-weight: bolder;">
        <td colspan="5" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{stotal}}</td>
    </tr>   
   {{/hasitems}}
</table>


