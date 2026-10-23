<table class="ctable" border="0" cellpadding="2" cellspacing="0">

    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="{{colspan}}">
            {{#t}}print.storeitems.stan_skladiv_na{{/t}} 
        </td>
    </tr>
 

    <tr style="font-weight: bolder;">

        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>
        {{#showcode}}<th style="border: solid black 1px">{{#t}}common.artykul{{/t}}</th>{{/showcode}}
        {{#showbrand}}<th style="border: solid black 1px">{{#t}}common.brend{{/t}}</th>{{/showbrand}}
        <th style="border: solid black 1px">{{#t}}common.min_kil{{/t}}</th>
        {{#cfnames}}
        <th style="border: solid black 1px">{{value}} </th>
        {{/cfnames}}
        {{#storescol}}
        <th style="border: solid black 1px">{{value}} </th>
        {{/storescol}}
        {{#ver}}
        <th style="border: solid black 1px" align="right"> {{#t}}common.kil{{/t}} </th>
        {{/ver}}
        
    </tr>
     {{^ver}}  
    {{#_detail}}       
       
    <tr>

        <td>{{itemname}}</td>
        {{#showcode}}<td data-type="s">{{item_code}}</td>{{/showcode}}
        {{#showbrand}}<td>{{brand}}</td>{{/showbrand}}
        <td align="right">{{minqty}}</td>
        {{#cfcol}}
          <td align="right">{{val}}</td>
        {{/cfcol}}
        {{#stlistcol}}
          <td align="right">{{qty}}</td>
        {{/stlistcol}}
    </tr>
 
    
    {{/_detail}}
    {{/ver}}
    {{#ver}}   
     {{#_detail}}       
      <tr style="font-weight: bolder;">
        <td   colspan="{{colspan}}">
            {{storename}}
        </td>
      </tr>    
        {{#items}}
    <tr>

        <td>{{itemname}}</td>
        {{#showcode}}<td data-type="s">{{item_code}}</td>{{/showcode}}
        {{#showbrand}}<td>{{brand}}</td>{{/showbrand}}
        <td align="right">{{minqty}}</td>
        {{#cfcol}}
          <td align="right">{{val}}</td>
        {{/cfcol}}
     <td align="right">{{qty}}</td>
    </tr>
 
    
    {{/items}}
  <tr  >
        <td   colspan="{{colspan}}">
               &nbsp;
        </td>
      </tr>      
    {{/_detail}}
    {{/ver}}   
</table>


