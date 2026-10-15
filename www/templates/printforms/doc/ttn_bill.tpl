<table class="ctable" border="0" cellpadding="1" cellspacing="0" >
    <tr>
        <td colspan="3">{{#t}}print.goodsissue_bill.nakladna{{/t}}</td>
    </tr>
    <tr>

        <td colspan="3">{{#t}}common.vid_2{{/t}}</td>
    </tr>
     {{#isfirm}}
    <tr>
        <td colspan="3"> {{#t}}common.prodavets_2{{/t}}</td>
    </tr>
    <tr>

        <td colspan="2"> {{firm_name}} 
        {{#fphone}} {{#t}}common.tel_4{{/t}}  {{/fphone}} </td>
    </tr>
    {{/isfirm}}
 
     {{#isfop}}
    <tr>
        <td colspan="3"> {{#t}}common.prodavets_2{{/t}}</td>
    </tr>
    <tr>

        <td colspan="2"> {{fop_name}} 
         </td>
    </tr>
    {{/isfop}}
 

  
    
    <tr>
        <td colspan="3"> {{#t}}common.pokupets_2{{/t}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{customer_name}}</td>
    </tr>
    <tr>
        <td colspan="3"> {{#t}}common.tel_3{{/t}}</td>
    </tr>
 
      {{#order}}
   
    <tr>

        <td colspan="3"> {{#t}}print.ttn_bill.zamovlennia{{/t}} 
         </td>
    </tr>
    {{/order}}

    {{#_detail}}
    <tr>
        <td colspan="3"> {{tovar_name}}</td>

    </tr>


    <tr>

        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>
    </tr>
    {{/_detail}}
    <tr>
        <td colspan="2" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{total}}</td>
    </tr>
                 
   

</table>