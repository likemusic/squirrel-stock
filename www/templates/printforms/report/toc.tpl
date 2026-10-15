<table class="ctable" cellspacing="0" cellpadding="1">
    <tr    >
        <td align="center" colspan="3">
            <h4  >{{#t}}print.toc.obmezhennia_systemy{{/t}} </h4>
        </td>
    </tr>
 
    {{#isdetail1}}
  <tr    >
        <td  colspan="3"  >
            <b  >{{#t}}print.toc.aktualnist_skladiv{{/t}}  </b>   
        </td>
    </tr>    
 <tr  >
        <td  colspan="3"  >
            <small  > {{#t}}print.toc.tovary_iakykh_ne_vyiavylos_na{{/t}} </small>   
        </td>
    </tr>  
   <tr>
       
        <th   style="border: solid black 1px"  >{{#t}}common.tovar{{/t}} </th>
        <th   style="border: solid black 1px"  >{{#t}}common.artykul{{/t}} </th>
        <th   style="border: solid black 1px" align="right">{{#t}}common.na_sumu{{/t}} </th>

    </tr>      
    {{#_detail1}}
    <tr>
       
        <td    >{{name}} </td>
        <td    >{{code}} </td>
        <td    align="right">{{amount}} </td>

    </tr>
    {{/_detail1}}
    
    {{/isdetail1}}
 
 
    {{#isdetail2}}
  <tr    >
        <td  colspan="3"  >
           <br> <b  >{{#t}}print.toc.zatrymka_vidpravky_tovaru_po_zamovlenniu{{/t}}</b>   
        </td>
    </tr>    
 <tr  >
        <td  colspan="3"  >
            <small  >   {{#t}}print.toc.dlia_zamovlen_z_riznytseiu_mizh{{/t}}  </small>   
        </td>
    </tr>  
   <tr>
       
        <th   style="border: solid black 1px"  >{{#t}}common.tovar{{/t}} </th>
        <th   style="border: solid black 1px"  >{{#t}}common.artykul{{/t}} </th>
        <th   style="border: solid black 1px" align="right">{{#t}}app.timesheet.dni{{/t}} </th>

    </tr>      
    {{#_detail2}}
    <tr>
       
        <td    >{{name}} </td>
        <td    >{{code}} </td>
        <td    align="right">{{days}} </td>

    </tr>
    {{/_detail2}}
    
    {{/isdetail2}}
    
    
  {{#isdetail3}}
  <tr    >
        <td  colspan="3"  >
           <br> <b  >{{#t}}print.toc.zatrymka_postavok_tmts{{/t}} </b>   
        </td>
    </tr>    
 <tr  >
        <td  colspan="3"  >
        
            <small    >  {{#t}}print.toc.dlia_zaiavok_z_riznytseiu_mizh{{/t}}       
         </small>   
        </td>
    </tr>  
   <tr>
       
        <th   style="border: solid black 1px"  >{{#t}}common.tovar{{/t}} </th>
        <th   style="border: solid black 1px"  >{{#t}}common.artykul{{/t}} </th>
        <th   style="border: solid black 1px" align="right">{{#t}}app.timesheet.dni{{/t}} </th>

    </tr>      
    {{#_detail3}}
    <tr>
       
        <td    >{{name}} </td>
        <td    >{{code}} </td>
        <td    align="right">{{days}} </td>

    </tr>
    {{/_detail3}}
    
    {{/isdetail3}}    
   
   
  {{#isdetail4}}
  <tr    >
        <td  colspan="3"  >
           <br> <b  >{{#t}}print.toc.zatrymka_postavok_tmts_pislia_oplaty{{/t}}   </b>   
        </td>
    </tr>    
 <tr  >
        <td  colspan="3"  >
            <small  >  {{#t}}print.toc.zatrymka_stvorennia_prybutkovoi_nakladno{{/t}}  </small>   
        </td>
    </tr>  
   <tr>
       
        <th colspan="2"  style="border: solid black 1px"  >{{#t}}common.postachalnyk{{/t}} </th>
     
        <th   style="border: solid black 1px" align="right">{{#t}}app.timesheet.dni{{/t}} </th>

    </tr>      
    {{#_detail4}}
    <tr>
       
        <td colspan="2"   >{{name}} </td>
       
        <td    align="right">{{days}} </td>

    </tr>
    {{/_detail4}}
    
    {{/isdetail4}}    
       
   {{#isdetail5}}
  <tr    >
        <td  colspan="3"  >
           <br> <b  >{{#t}}common.nelikvidni_tovary{{/t}}  </b>   
        </td>
    </tr>    
 <tr  >
        <td  colspan="3"  >
            <small  > {{#t}}print.toc.tovary_na_skladakh_iaki_ne{{/t}} </small>   
        </td>
    </tr>  
   <tr>
       
        <th   style="border: solid black 1px"  >{{#t}}common.tovar{{/t}} </th>
        <th   style="border: solid black 1px"  >{{#t}}common.artykul{{/t}} </th>
        <th   style="border: solid black 1px" align="right">{{#t}}common.na_sumu{{/t}} </th>

    </tr>      
    {{#_detail5}}
    <tr>
       
        <td    >{{itemname}} </td>
        <td    data-type="s" >{{item_code}} </td>
        <td    align="right">{{amount}} </td>

    </tr>
    {{/_detail5}}
    
    {{/isdetail5}}
   
    
</table>


