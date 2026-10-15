<table  class="ctable"  >
 
    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="2">
            {{#t}}print.balance.upravlinskyi_balans{{/t}}
        </td>
    </tr>
  
    <tr>

        <td align="center" colspan="2">
            {{#t}}print.balance.na{{/t}}  <br>
        </td>
    </tr>
 
 
   <tr>

        <td colspan="2">            <b>{{#t}}print.balance.aktyvy{{/t}}</b>         </td>
    </tr>
    {{#amat}}
    <tr>
        <td > {{#t}}common.materialy_ta_komplektuiuchi{{/t}}        </td>
        <td align="right"> {{amat}}  </td>
    </tr>
    {{/amat}}
   
    {{#aprod}}    
    <tr>
        <td > {{#t}}print.balance.napivfabrykaty_ta_hotova_produktsiia{{/t}}        </td>
        <td align="right">  {{aprod}}      </td>
    </tr>
    {{/aprod}} 
    {{#ambp}}      
    <tr>
        <td >   {{#t}}common.mshp{{/t}}        </td>
        <td align="right">     {{ambp}}               </td>
    </tr>
    {{/ambp}}   

    {{#aitem}}                 
    <tr>
        <td >{{#t}}common.tovary{{/t}}        </td>
        <td align="right">    {{aitem}}      </td>
    </tr>
    {{/aitem}}                 
    {{#aother}}                 
    <tr>
        <td >  {{#t}}print.balance.inshi_tmts{{/t}}    </td>
        <td align="right">     {{aother}}     </td>
    </tr>
    {{/aother}}                     
    {{#anal}}                 
    <tr>
        <td >  {{#t}}common.hotivka{{/t}}        </td>
        <td align="right">   {{anal}}                        </td>
    </tr>
    {{/anal}}                     
    {{#abnal}}                 
    <tr>
        <td >  {{#t}}common.bezhotivka{{/t}}        </td>
        <td align="right">  {{abnal}}   </td>
    </tr>
    {{/abnal}}                     
    {{#debet}}
    <tr>  
        <td >{{#t}}print.balance.borh_kontrahentiv{{/t}}       </td>
        <td align="right">     {{debet}}              </td>
    </tr>
    {{/debet}} 
    {{#aemp}}                 
    <tr>
        <td >  {{#t}}print.balance.spivrobitnyky_vydani_avansy_toshcho{{/t}}        </td>
        <td align="right">        {{aemp}}             </td>
    </tr>
    {{/aemp}}                 
   {{#aeq}}                 
    <tr>
        <td >  {{#t}}print.balance.balansova_vartist_oz{{/t}}        </td>
        <td align="right">        {{aeq}}             </td>
    </tr>
    {{/aeq}}                 
    
    {{#aprodrest}}                 
    <tr>
        <td >  {{#t}}print.balance.nezavershene_vyrobnytstvo{{/t}}       </td>
        <td align="right">        {{aprodrest}}             </td>
    </tr>
    {{/aprodrest}}                 
    
    
     <tr style="font-weight: bolder;">
        <td align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{atotal}}</td>
    </tr>   
    
   <tr>
        <td colspan="2">     <b>{{#t}}print.balance.pasyvy{{/t}}</b>        </td>    </tr>

    {{#credit}}
    <tr>  
        <td >  {{#t}}print.balance.borh_kontrahentam{{/t}}       </td>
        <td align="right">     {{credit}}              </td>
    </tr>
    {{/credit}} 
 
    {{#pemp}}                 
    <tr>
        <td >  {{#t}}print.balance.spivrobitnyky_zarplata_do_vydachi_toshch{{/t}}        </td>
        <td align="right">    {{pemp}}               </td>
    </tr>
     {{/pemp}}

     <tr style="font-weight: bolder;">
        <td align="right">{{#t}}common.vsoho{{/t}}</td>
        <td align="right">{{ptotal}}</td>
    </tr>   
     <tr >
        <td ></td>
        <td ></td>
    </tr>   
     <tr style=";font-weight: bolder;">
        <td align="right">{{#t}}print.balance.balans{{/t}}</td>
        <td align="right">{{bal}}</td>
    </tr>   
    
</table>


