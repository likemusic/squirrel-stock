   <div style="border: 1px solid   #ccc;width:100%;;">
    <table class="ctable" border="0" cellpadding="2" cellspacing="0" style="width:100%"  > 
    <tr>   <td>
           <table>
            <tr  >
                <td   colspan="3"  ><b> {{name}}</b></td>
            </tr>
           <tr  >
                <td colspan="3"  >{{#t}}print.item_sticker.kod{{/t}}</td>
            </tr>
            <tr  >
                <td  >{{#t}}common.vaha{{/t}}</td>
                <td  >{{#t}}common.tsina{{/t}}</td>
                <td style="font-size:18px" ><b> {{#t}}common.suma{{/t}}</b></td>
            </tr>
            <tr  >
                <td  >{{qty}}</td>
                <td  >{{price}}</td>
                <td  ><b> {{sum}}</b></td>
            </tr>
             {{#term}}
             <tr  >
                <td     colspan="3">
               {{#t}}print.item_sticker.prydatnyi_do{{/t}}
                 </td>
                </tr> 
           {{/term}}        
            <tr  >
                <td style="padding:20px;" align="center" colspan="3">
                <img style="width:80%" src="{{dataUri}}" >
                 </td>
                </tr>

          
     
            
          </table>
      </td></tr>
    </table>
</div>   