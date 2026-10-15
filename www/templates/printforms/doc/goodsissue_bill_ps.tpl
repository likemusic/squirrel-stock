<align>center</align>
<font bold="true">a</font>
<text>{{#t}}print.goodsissue_bill_ps.nakladna{{/t}}</text>
<font >a</font>
<text>{{#t}}common.vid_2{{/t}}</text>
<align>left</align>
<text>{{#t}}print.goodsissue_bill_ps.prodavets{{/t}}</text>

    {{#isbank}}
<text>{{bank}}</text>
<text>{{#t}}print.goodsissue_bill.r_r{{/t}}</text>    
   {{/isbank}}
<text>{{#t}}common.tel_3{{/t}}</text>
<text>{{#t}}common.pokupets_3{{/t}}</text>
<separator>-</separator>
 {{#_detail}}
 <text>{{tovar_name}}</text>
<row>
  <col align="right" length="12" >{{quantity}}</col>
  <col align="right" length="10" >{{price}}</col>
  <col align="right" length="10" >{{amount}}</col>
</row>
{{/_detail}}
<font bold="true">a</font>
<separator>-</separator>
<align>right</align>
<text>{{#t}}common.vsoho_2{{/t}}</text>
<newline ></newline>
