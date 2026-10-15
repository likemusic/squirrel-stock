<table class="ctable" border="0" cellpadding="1" cellspacing="0"  >
    
  
    <tr>
        <td colspan="3">{{#t}}print.orderfood_billforpay.rakhunok{{/t}}</td>
    </tr>
    
    
 
 
    <tr>

        <td colspan="3">{{#t}}common.vid_3{{/t}}</td>
    </tr>
    <tr>

        <td colspan="3"> {{firm_name}}</td>
    </tr>
 
 
 

    <tr>

        <td colspan="3"> {{address}}</td>
    </tr>
 
    {{#customer_name}}
    <tr>
        <td colspan="3"> {{#t}}common.pokupets_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{customer_name}}</td>
    </tr>

    {{/customer_name}}

 


    {{#_detail}}
    <tr>
        <td colspan="3">{{tovar_name}}</td>

    </tr>


    <tr>

        <td colspan="2" align="right">{{quantity}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    
    {{/_detail}}
    <tr>
        <td colspan="2" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{total}}</td>
    </tr>

   
    {{#isdisc}}
    <tr  >
        <td colspan="2" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/isdisc}}
   {{#bonus}}
    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.spysano_bonusiv{{/t}}</td>
        <td align="right">{{bonus}}</td>
    </tr>
    {{/bonus}}

    <tr style="font-weight: bolder;">
        <td colspan="2" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td style="font-size:larger" align="right">{{payamount}}</td>
    </tr>
    
                      
                    
</table>