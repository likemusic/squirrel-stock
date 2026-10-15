 <table class="table  table-sm">
   <tr><th colspan="2">{{name}}</th></tr>
   <tr><td>{{#t}}common.tel_2{{/t}}</td><td>{{phone}}</td></tr>
   <tr><td>{{#t}}common.iedrpou{{/t}}</td><td>{{edrpou}}</td></tr>
   {{#smscode}} 
   <tr><td colspan="2">   {{#t}}print.cinfo.sms_kod_nbsp_nbsp{{/t}}<b>{{smscode}}   </b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a {{{click}}} href="javascript:void(0);return false;">{{#t}}common.vidpravyty{{/t}}</a>   
   <br><small> {{#t}}print.cinfo.vidpravka_kodu_na_telefon_dlia{{/t}}   </small>
   </td></tr>
  {{/smscode}}
   
   {{#email}}
     <tr><td>E-mail</td><td>{{{email}}}</td></tr>
   {{/email}}  
   {{#viber}}
     <tr><td>Viber</td><td>{{{viber}}}</td></tr>
   {{/viber}}  
   {{#telega}}
     <tr><td>{{#t}}common.telehram{{/t}}</td><td>{{{telega}}}</td></tr>
   {{/telega}}  
   {{#address}}   
     <tr><td>{{#t}}common.adresa_2{{/t}}</td><td>{{address}}</td></tr>
   {{/address}}   
      
   {{#bonus}}
   <tr><td>{{#t}}common.bonusy{{/t}}</td><td>   {{bonus}}</td></tr>
   {{/bonus}}
  {{#dolg}}
   <tr><td>{{#t}}print.cinfo.borh{{/t}}  </td><td>   {{dolg}} <small>{{#t}}print.cinfo.debet_kredyt{{/t}}</small></td></tr>
   {{/dolg}}
  {{#disc}}
   <tr><td>{{#t}}print.cinfo.postiina_znyzhka{{/t}}</td><td>   {{disc}}</td></tr>

   {{/disc}}
   <tr><td>{{#t}}print.cinfo.pokupok_na_sumu{{/t}}</td><td>   {{sumall}}</td></tr>
   
   {{#comment}}   
   <tr><td colspan="2">{{#t}}print.cinfo.prymitka{{/t}}</td></tr>
   {{/comment}}    
   
   {{#last}}
     <tr><td colspan="2"> {{#t}}print.cinfo.ostannii_dokument_vid_na_sumu{{/t}}</td></tr>
     <tr><td colspan="2"> {{#t}}print.cinfo.ostanni_tovary{{/t}} </td></tr>
     <tr><td colspan="2"> 
         <table      style="font-size:smaller">
            {{#goods}}
             <tr><td >{{itemname}} </td><td class="text-nowrap  ">{{item_code}}</td></tr>
            {{/goods}}
         
         </table>
       </td></tr>
      
      
     
   {{/last}}
   

 </table>