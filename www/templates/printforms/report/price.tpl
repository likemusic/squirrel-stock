<table class="ctable" border="0"   cellpadding="2" cellspacing="0">


    <tr>

        <td style="font-size:larger" align="center" colspan="8">
          <b>  {{#t}}print.price.prais_vid{{/t}}</b><br><br>
        </td>
    </tr>
 {{^iscat}}   
    <tr>
        <td  colspan="11">
        <b>  {{#t}}print.price.katehoriia{{/t}}</b> {{catname}} <br>
        </td>
    </tr>
 {{/iscat}}  
 {{^isbrand}}      
    <tr>
        <td  colspan="8">
        <b>  {{#t}}print.price.brend{{/t}}</b> {{brandname}}  <br>
        </td>
    </tr>
 {{/isbrand}}  
    <tr style="font-weight: bolder;">
        {{#showimage}}
        <th> </th>
        {{/showimage}}
        <th>{{#t}}common.naimenuvannia{{/t}}</th>
        <th>{{#t}}common.kod{{/t}}</th>
        <th>{{#t}}common.od_vym{{/t}}</th>
     {{#iscat}}   <th>{{#t}}common.katehoriia{{/t}}</th>  {{/iscat}}
     {{#isbrand}}     <th>{{#t}}common.brend{{/t}}</th> {{/isbrand}} 
{{#showqty}}      <th align="right">{{#t}}common.kil{{/t}}</th>{{/showqty}}
        
{{#price1name}}          <th align="right">{{price1name}}</th>  {{/price1name}} 
{{#price2name}}          <th align="right">{{price2name}}</th>  {{/price2name}} 
{{#price3name}}          <th align="right">{{price3name}}</th>  {{/price3name}} 
{{#price4name}}          <th align="right">{{price4name}}</th>  {{/price4name}} 
{{#price5name}}          <th align="right">{{price5name}}</th>  {{/price5name}} 
{{#showdesc}}      <th align="right">{{#t}}common.opys{{/t}}</th>{{/showdesc}}
 <th>{{#t}}common.prymitka{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        {{#showimage}}
        <td  >
          {{#isimage}}
          <img src="{{im}}"  style="width:64px" />
           {{/isimage}}
        </td>
        {{/showimage}}

        <td >{{name}}</td>
        <td >{{code}}</td>
        <td >{{msr}}</td>

  {{#iscat}}        <td >{{cat}}</td>     {{/iscat}}  
  {{#isbrand}}      <td >{{brand}}</td>   {{/isbrand}}  
{{#showqty}}        <td  align="right">{{qty}}</td> {{/showqty}}
{{#price1name}}         <td  align="right">{{price1}}</td> {{/price1name}} 
{{#price2name}}         <td  align="right">{{price2}}</td> {{/price2name}} 
{{#price3name}}         <td  align="right">{{price3}}</td> {{/price3name}} 
{{#price4name}}         <td  align="right">{{price4}}</td> {{/price4name}} 
{{#price5name}}         <td  align="right">{{price5}}</td> {{/price5name}} 
{{#showdesc}}           <td >{{desc}}</td>{{/showdesc}}
        <td >{{notes}}</td>
    </tr>
    {{/_detail}}
</table>
<br>

