<table class="ctable" border="0"   cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="9">
           {{#t}}print.formitemsreport.forma_vedennia_obliku_tovarnykh_zapasiv{{/t}}
        </td>
    </tr>
    <tr style="font-size:larger;  ">
        <td align="center" colspan="9">
           {{firmname}}
        </td>
    </tr>
 
 

    <tr  >

        <th rowspan="2" style="border: solid black 1px">№</th>

        <th rowspan="2" style="border: solid black 1px">{{#t}}print.formitemsreport.data_vnesennia_zapysu{{/t}}</th>
        <th colspan="6" style="border: solid black 1px">{{#t}}print.formitemsreport.rekvizyty_pervynnoho_dokumenta_shcho_pid{{/t}} </th>

        <th rowspan="2"   style="border: solid black 1px">{{#t}}print.formitemsreport.prymitky{{/t}}  </th>
        
    </tr>
    <tr> 
    <th   style="border: solid black 1px">{{#t}}print.formitemsreport.vyd{{/t}}</th>
    <th   style="border: solid black 1px">{{#t}}common.data{{/t}}</th>
    <th   style="border: solid black 1px">№</th>
    <th   style="border: solid black 1px">{{#t}}print.formitemsreport.postachalnyk_prodavets_vyrobnyk_abo_otry{{/t}}</th>
    <th   style="border: solid black 1px">{{#t}}print.formitemsreport.nadkhodzhennia_tovaru_prydbannia_poverne{{/t}}</th>
    <th   style="border: solid black 1px">{{#t}}print.formitemsreport.vybuttia_tovaru_prodazh_tovariv_v{{/t}}
 
</th>
    
    
    </tr>
    
    {{#_detail}}
    <tr>

        <td>{{nrec}}</td>

        <td>{{datedec}}</td>
        <td>{{doctype}}</td>

        <td  >{{docdate}}</td>
        <td  >{{docno}}</td>
        <td  >{{cust}}</td>
        <td align="right">{{in}}</td>
        <td align="right">{{out}}</td>
        <td  > </td>
 
    </tr>
    {{/_detail}}
  
   
   
</table>


