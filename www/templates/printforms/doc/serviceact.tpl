<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr>
        <td colspan="6">
            {{#t}}print.serviceact.zamovnyk{{/t}}
        </td>


    </tr>
    {{#isfirm}}
    <tr>
        <td colspan="7">
            {{#t}}print.serviceact.vykonavets{{/t}}
        </td>

    </tr>
    {{/isfirm}}
    {{#iscontract}}
    <tr>
        <td colspan="7">
            {{#t}}print.incomeservice.dohovir_vid{{/t}}
        </td>

    </tr>
    {{/iscontract}}

    {{#isdevice}}
    <tr>
        <td colspan="7">
           {{#t}}print.serviceact.pryiniato_vid_zamovnyka_s_n{{/t}}
        </td>

    </tr>
    {{/isdevice}}
    <tr style="font-weight: bolder;">
        <td colspan="7" align="center">
           
          {{#isfinished}}  {{#t}}common.akt_vykonanykh_robit{{/t}} {{/isfinished}} 
          {{^isfinished}}  {{#t}}common.kvytantsiia_do{{/t}} {{/isfinished}} 
           {{#t}}print.serviceact.vid{{/t}}
        </td>
    </tr>

</table>
<br>
<table class="ctable"   cellspacing="0" cellpadding="1" border="0">
    <tr style="font-weight: bolder;">
        <th   style="border: 1px solid black;">№</th>
        <th style="border: 1px solid black;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: 1px solid black;"> </th>
        <th style="border: 1px solid black;"  >{{#t}}common.od{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.kil{{/t}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.tsina{{/t}}  {{#nds}} {{#t}}common.bez_pdv{{/t}}  {{/nds}}</th>
        <th style="border: 1px solid black;" align="right">{{#t}}common.suma{{/t}}</th>

    </tr>
    {{#_detail}}
    <tr>
        <td>{{no}}</td>
        <td>{{service_name}}</td>

        <td>{{desc}}</td>

        <td  >{{msr}}</td>
        <td align="right">{{qty}}</td>
        <td align="right">{{price}}   {{#nds}} ({{pricenonds}})  {{/nds}}  </td>
        <td align="right">{{amount}}</td>

    </tr>
    {{/_detail}}
    <tr style="font-weight: bolder;">
        <td colspan="6" style="border-top: 1px solid black;" align="right">{{#t}}common.vsoho{{/t}}</td>
        <td style="border-top: 1px solid black;" align="right">{{total}}</td>
    </tr>
    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.znyzhka_2{{/t}}</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}  
  {{#nds}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.v_t_ch_pdv{{/t}}</td>
        <td align="right">{{nds}}</td>
    </tr>
    {{/nds}}  
    
      
   {{#bonus}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}print.serviceact.spysani_bonusy{{/t}}</td>
        <td align="right">{{bonus}}</td>
    </tr>
    {{/bonus}}    
   {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.do_splaty_2{{/t}}</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}} 
   {{#payed}}  
    <tr style="font-weight: bolder;">
        <td colspan="6" align="right">{{#t}}common.oplata_2{{/t}}</td>
        <td align="right">{{payed}}</td>
    </tr>
     {{/payed}}  
    <tr>
        <td colspan="7"><br>
            {{#t}}print.serviceact.harantiia{{/t}}
        </td>
    </tr>
    <tr>
        <td colspan="7"  ><small>{{{devdesc}}}</small></td>
    </tr>
    <tr>
        <td colspan="7"><small>{{{notes}}}</small></td>
    </tr>
       {{#payamount}}
   
   {{#totalstr}}
    <tr>
        <td colspan="7">{{#t}}common.do_splaty{{/t}} <b>{{totalstr}}</b></td>
   </tr>
   {{/totalstr}}                    

              {{/payamount}} 
                    <tr>
                        <td colspan="3" > 
                            {{#t}}print.serviceact.vykonavets_2{{/t}}
                        </td>
                        <td colspan="2"> 
                            {{#t}}print.serviceact.zamovnyk_2{{/t}}
                        </td>

                    </tr>
                    <tr>
                        <td> </td>
                        <td colspan="4">
                            {{#isstamp}}
                            <img style="height:100px;" src="{{stamp}}"/>
                            {{/isstamp}}

                            {{^isstamp}}
                        
                            <br><br>
                            {{#t}}print.goodsissue.nbsp_nbsp_nbsp_nbsp_nbsp{{/t}}
                            <br><br>
                            {{/isstamp}}
                        </td>


                    </tr>
          
              
</table>


