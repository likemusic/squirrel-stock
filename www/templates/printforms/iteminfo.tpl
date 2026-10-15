 <table class="table  table-sm">
     <tr><th colspan="2">{{itemname}}</th></tr>
 
     <tr><td>{{#t}}common.artykul{{/t}}</td><td>{{item_code}}</td></tr>
     <tr><td>{{#t}}common.shtrykh_kod{{/t}}</td><td>{{bar_code}}</td></tr>
     <tr><td>{{#t}}common.brend{{/t}}</td><td>{{brand}}</td></tr>
     <tr><td>{{#t}}common.katehoriia{{/t}}</td><td>{{cat_name}}</td></tr>
     <tr><td>{{#t}}common.na_skladi{{/t}}</td><td>{{qty}}</td></tr>
     <tr><td>{{#t}}common.tsina{{/t}}</td><td>{{price}}</td></tr>
     <tr><td colspan="2"><small>{{notes}}</small></td></tr>
     {{#image}}
     <tr><td colspan="2"><img style="height:128px" src="{{image}}"> </td></tr>
     {{/image}}
 
 
   

 </table>