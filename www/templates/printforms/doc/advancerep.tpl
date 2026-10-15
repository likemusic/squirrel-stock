<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td colspan="6" align="center">
            <b> {{#t}}print.advancerep.avansovyi_zvit_vid{{/t}}</b> <br>
        </td>
    </tr>
    {{#isdetail}}
    <tr>
        <td><b>{{#t}}common.na_sklad_2{{/t}}</b> </td> 
        <td colspan="5">
             {{to}}
        </td>
    </tr>

   {{#storeemp}}
    <tr>
         <td><b>{{#t}}common.na_spivrobitnyka_2{{/t}}</b> </td> 
       <td colspan="5">
             {{storeemp}}
        </td>
    </tr>
 
    {{/storeemp}}    
     {{/isdetail}} 
    <tr>
          <td><b>{{#t}}print.advancerep.vid_spivrobitnyka{{/t}}</b> </td> 
      <td colspan="5">
             {{emp}}
        </td>
    </tr>
    {{#examount}}
    <tr>
         <td><b>{{#t}}print.advancerep.suma_povernennia{{/t}}</b> </td> 
       <td colspan="5">
             {{examount}}
        </td>
    </tr>
   {{/examount}}  
  {{#spentamount}}
    <tr>
         <td><b>{{#t}}print.advancerep.suma_vytrat{{/t}}</b> </td> 
       <td colspan="5">
             {{spentamount}}
        </td>
    </tr>
   <tr>
         <td><b>{{#t}}print.advancerep.typ_vytrat{{/t}}</b> </td> 
       <td colspan="5">
             {{spenttypename}}
        </td>
    </tr>
   {{/spentamount}}  

    {{#isdetail}}  
    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;"></th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>


        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kil{{/t}}</th>
        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.tsina{{/t}}</th>
        <th align="right"   style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.suma{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>

        <td>{{item_name}}</td>
        <td  data-type="s">{{item_code}}</td>

        <td align="right"  data-type="s">{{snumber}}</td>
        <td>{{msr}}</td>
        <td align="right">{{quantity}}</td>
        <td align="right">{{price}}</td>
        <td align="right">{{amount}}</td>

    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="6" align="right">{{#t}}common.na_sumu_2{{/t}}</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>
    
    {{/isdetail}}  
    
    
    <tr>
        <td colspan="6">
            {{{notes}}}
        </td>

    </tr>
</table>



