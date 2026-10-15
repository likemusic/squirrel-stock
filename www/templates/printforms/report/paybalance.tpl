<table  class="ctable"  >
 
    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="3">
            {{#t}}print.paybalance.prybutky_ta_vydatky{{/t}}
        </td>
    </tr>
   
    <tr>

        <td align="center" colspan="3">
            {{#t}}common.period_z_po_2{{/t}} <br>
        </td>
    </tr>
     <tr>
        <td colspan="3">
            <b>{{#t}}print.paybalance.prybutky{{/t}}</b>
        </td>
    </tr>


    {{#_detail}}
    <tr>

        <td>{{type}}</td>

        <td align="right">{{in}}</td>
        <td></td>
    </tr>
    {{#docdet}}
   
    
    <tr>

        <td style="font-size:smaller">&nbsp;&nbsp;{{docdesc}}</td>

        <td style="font-size:smaller" align="right">{{indet}}</td>
       <td></td>
    </tr>   
    {{/docdet}}
    
    {{/_detail}}
    <tr style="font-weight: bolder;">

        <td align="right">{{#t}}common.vsoho{{/t}}</td>

        <td align="right">{{tin}}</td>
        <td></td>
    </tr>
    <tr>

        <td colspan="3">
            <b>{{#t}}print.paybalance.vydatky{{/t}}</b> 
        </td>
    </tr>


    {{#_detail2}}
    <tr>

        <td>{{type}}</td>

        <td align="right">{{out}}</td>
        <td></td>
    </tr>            
    {{#docdet}}
    
    <tr>

        <td style="font-size:smaller">&nbsp;&nbsp;{{docdesc}}</td>

        <td style="font-size:smaller" align="right">{{indet}}</td>
       <td></td>
    </tr>   
    {{/docdet}}    
    
    {{/_detail2}}
    <tr style="font-weight: bolder;">

        <td align="right">{{#t}}common.vsoho{{/t}}</td>

        <td align="right">{{tout}}</td>
        <td></td>
    </tr>
    <tr style="font-weight: bolder;">

        <td align="right">{{#t}}print.balance.balans{{/t}}</td>

        <td align="right">{{total}}</td>
        <td></td>
    </tr>

   {{#is3}}    
    
   
     <tr> 
        <td colspan="3"> <br>
            <b>{{#t}}print.paybalance.detalizatsiia_po_tmts{{/t}} </b>
        </td>       
    </tr>    
    
     {{#_detail3}}
    <tr>

        <td>{{type}}</td>

        <td align="right">{{out}}</td>
        <td></td>
    </tr>
     {{/_detail3}}
   {{/is3}}  
   
  {{#isfin}}  
  <tr style="font-weight: bolder;">

        <td> <br>{{#t}}print.paybalance.finansovi_pokaznyky{{/t}}</td>

        <td></td>
        <td></td>

    </tr>
    <tr>
        <td>{{#t}}print.paybalance.prokhod{{/t}}</td>
        <td align="right"> {{tu}} </td>
        <td>
            <i class="fa fa-info-circle  " data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{#t}}print.paybalance.vyruchka_vidpuskna_tsina_na_kilkist{{/t}}"></i>
        </td>
    </tr>
    <tr>
        <td>{{#t}}print.paybalance.zminni_vytraty{{/t}}</td>
        <td align="right"> {{tvc}} </td>
        <td>
            <i class="fa fa-info-circle  " data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{#t}}print.paybalance.vytraty_osnovnoi_diialnosti{{/t}}"></i>
        </td>
    </tr>
    <tr>
        <td>{{#t}}print.paybalance.operatsiini_vytraty{{/t}}</td>
        <td align="right">{{OP}}  </td>
        <td>
            <i class="fa fa-info-circle  " data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{#t}}print.paybalance.vsi_vytraty_minus_zminni_vytraty{{/t}} "></i>
        </td>
    </tr>
    <tr>
        <td>{{#t}}print.paybalance.chystyi_prybutok{{/t}}</td>
        <td align="right"> {{PR}} </td>
        <td>
            <i class="fa fa-info-circle  " data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{#t}}print.paybalance.prokhid_minus_operatsiini_vytraty_vyruch{{/t}}"></i>
        </td>
    </tr>
   {{#isinv}}   
    <tr>
        <td>{{#t}}print.paybalance.investytsii{{/t}}</td>
        <td align="right"> {{inv}} </td>
        <td>
            <i class="fa fa-info-circle  " data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{#t}}print.paybalance.tmts_na_skladi_ta_osnovni{{/t}}"></i>
        </td>
    </tr>
    <tr>
        <td>{{#t}}print.paybalance.viddacha_vid_investytsii_roi{{/t}}</td>
        <td align="right"> {{ROI}} </td>
        <td>
            <i class="fa fa-info-circle  " data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{#t}}print.paybalance.prokhid_na_investytsii{{/t}}"></i>
        </td>
    </tr>
    {{/isinv}}     
    {{/isfin}}     
</table>


