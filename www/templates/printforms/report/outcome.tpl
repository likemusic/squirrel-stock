<table class="ctable" border="0" cellpadding="2" cellspacing="0">


    <tr>

        <td style="  font-weight: bolder;" align="center" colspan="7">
            {{#t}}common.period_z_po_2{{/t}} <br>  
        </td>
    </tr>
    {{#_type1}}
    <tr style="font-size:larger; font-weight: bolder;">
        <td align="center" colspan="7">
            {{#t}}print.outcome.prodazhi_za_tovaramy{{/t}} <br 
        </td>
    </tr>
    <tr style=" font-weight: bolder;">
    <td align="center" colspan="7">
        {{detname}} <br>  
    </td>
</tr>
    <tr style="font-weight: bolder;">
        <th align="center" style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>

        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.na_sumu{{/t}}</th>
      {{^noshowpartion}}
        <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
      {{/noshowpartion}}

      <th> </th>
    </tr>
    {{#_detail}}
    <tr>


        <td align="right">{{docs}}</td>
        <td>{{name}}</td>
        <td>{{code}}</td>

        <td align="right">{{qty}}</td>
        <td align="right">{{summa}}</td>

      {{^noshowpartion}}


        {{#navarsign}}
        <td align="right">{{navar}}</td>
        {{/navarsign}}
        {{^navarsign}}
        <td align="right" style="color:red">{{navar}}</td>
        {{/navarsign}}
        <td align="right" >{{navarproc}}</td>
      {{/noshowpartion}}

      <td>{{descr}} </td>
    </tr>
    {{/_detail}}
    <tr><td colspan="4"></td><td align="right" ><b>{{totsumma}}</b></td>
      {{^noshowpartion}}

    <td align="right" ><b>{{totnavar}}</b></td> 
    <td align="right" ><b>{{totnavarproc}}</b></td> 
      {{/noshowpartion}}
    
      <td>  </td>
    </tr>

</table>
{{/_type1}}
{{#_type2}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_pokuptsiamy{{/t}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
    <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
    <th colspan="3" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
    
        {{^noshowpartion}}

    <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
    <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

    <th></th>    
    <th></th>    
</tr>
{{#_detail}}
<tr>
        <td>{{docs}}</td>


    <td colspan="3">{{name}}</td>


    <td align="right">{{summa}}</td>
    {{^noshowpartion}}
    
    <td align="right">{{navar}}</td>
        <td align="right" >{{navarproc}}</td>
    {{/noshowpartion}}
    
    <td></td>  
    <td>{{descr}}</td>  
</tr>
{{/_detail}}
<tr><td colspan="4"></td><td align="right" ><b>{{totsumma}}</b></td>
    {{^noshowpartion}}

<td align="right" ><b>{{totnavar}}</b> </td> 
<td align="right" ><b>{{totnavarproc}}</b> </td> 

    {{/noshowpartion}}
    <td></td>  
    <td></td>  
</tr>

</table>
{{/_type2}}
{{#_type3}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_datamy{{/t}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
     <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
   <th style="border: solid black 1px;width:120px;">{{#t}}common.data{{/t}}</th>
 
    <th align="right" style="border: solid black 1px;width:100px;">{{#t}}common.na_sumu{{/t}}</th>
    
    <th></th>
    <th></th>
    <th></th>
    <th></th>

</tr>
{{#_detail}}
<tr>
         <td align="right">{{docs}}</td>


    <td>{{dt}}</td>

                                     
    <td align="right">{{summa}}</td>
  
    <td></td>
    <td></td>
    <td></td>
    <td> </td>

</tr>
{{/_detail}}
<tr><td colspan="3" align="right" ><b>{{totsumma}}</b></td> 
<td   > </td> 
<td   > </td> 
<td   > </td> 
<td   > </td> 
</tr>

</table>
{{/_type3}}
{{#_type4}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.income.posluhy_ta_roboty{{/t}} <br><br>
    </td>
</tr>
<tr style="font-weight: bolder;">
     <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
   <th colspan="3" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
  
    <th></th>
    <th></th>
</tr>
{{#_detail}}
<tr>
        <td align="right">{{docs}}</td>
        <td colspan="3" align="right">{{name}}</td>


 

    <td align="right">{{summa}}</td>
 
    <td></td>
    <td>{{descr}}</td>
</tr>
{{/_detail}}
 <tr><td colspan="4"></td>
 <td align="right" ><b>{{totsumma}}</b></td>
   

    <td></td>   
    <td></td>   
</tr>    
 
</table>
{{/_type4}}

{{#_type5}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_katehoriiamy{{/t}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
     <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
   <th colspan="3" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
 
     {{^noshowpartion}}

    <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
    <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

    <th></th>
    <th></th>
</tr>
{{#_detail}}
<tr>
        <td align="right">{{docs}}</td>


    <td colspan="3">{{name}}</td>


    <td align="right">{{summa}}</td>
      {{^noshowpartion}}

  <td align="right">{{navar}}</td>
        <td align="right" >{{navarproc}}</td>
  
    {{/noshowpartion}}
  
  
    <td>{{descr}}</td>
</tr>
{{/_detail}}
<tr><td colspan="4" ></td> <td align="right" ><b>{{totsumma}}</b></td> 
    {{^noshowpartion}}

<td align="right"   > <b>{{totnavar}}</b></td>
<td align="right"   > <b>{{totnavarproc}}</b></td>
    {{/noshowpartion}}
    <td> </td>
    <td> </td>

 </tr>

</table>
{{/_type5}}     

{{#_type6}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_kompaniiamy{{/t}} <br> <br>
    </td>
</tr>
<tr style=" font-weight: bolder;">
    <td align="center" colspan="7">
        {{detname}} <br>  
    </td>
</tr>
<tr style="font-weight: bolder;">
      <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
  <th colspan="3" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
      {{^noshowpartion}}

    <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
    <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

    <th></th>
    <th></th>
</tr>
{{#_detail}}
<tr>

       <td align="right">{{docs}}</td>

    <td colspan="3">{{name}}</td>


    <td align="right">{{summa}}</td>
     {{^noshowpartion}}

   <td align="right">{{navar}}</td>
        <td align="right"  >{{navarproc}}</td>
   
    {{/noshowpartion}}
   
    <td></td>
    <td>{{descr}}</td>
</tr>
{{/_detail}}
<tr><td colspan="4" ></td> 
<td align="right" ><b>{{totsumma}}</b></td>
    {{^noshowpartion}}

 <td align="right"   > <b>{{totnavar}}</b></td>
 <td align="right"   > <b>{{totnavarproc}}</b></td>
    {{/noshowpartion}}
 
       <td> </td>

  </tr>

</table>
{{/_type6}}     

{{#_type7}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_skladamy{{/t}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
     <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
   <th colspan="3" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
     {{^noshowpartion}}

   <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
   <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

    <th></th>
    <th></th>
</tr>
{{#_detail}}
<tr>

         <td align="right">{{docs}}</td>

    <td colspan="3">{{name}}</td>


    <td align="right">{{summa}}</td>
      {{^noshowpartion}}

  <td align="right">{{navar}}</td>
        <td align="right" >{{navarproc}}</td>
  
    {{/noshowpartion}}
  
    <td></td>
    <td>{{descr}}</td>
</tr>
{{/_detail}}
<tr><td colspan="4" ></td> <td align="right" ><b>{{totsumma}}</b></td>
     {{^noshowpartion}}

 <td align="right"   > <b>{{totnavar}}</b></td>
 <td align="right"   > <b>{{totnavarproc}}</b></td>
    {{/noshowpartion}}
       <td> </td>

 </tr>

</table>
{{/_type7}}     
{{#_type8}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_dzherelamy{{/t}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
    <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
    <th colspan="2" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

         <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>

    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
      {{^noshowpartion}}

    <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
    <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

       <th> </th>
   
</tr>
{{#_detail}}
<tr>
        <td align="right">{{docs}}</td>


    <td colspan="2">{{name}}</td>


    <td align="right">{{qty}}</td>
    <td align="right">{{summa}}</td>
      {{^noshowpartion}}

  <td align="right">{{navar}}</td>
        <td align="right" >{{navarproc}}</td>
  
    {{/noshowpartion}}
       <td> {{descr}}</td>
    
</tr>
{{/_detail}}
<tr><td colspan="4" ></td> <td align="right" ><b>{{totsumma}}</b></td> 
    {{^noshowpartion}}

    <td align="right"   > <b>{{totnavar}}</b></td>
    <td align="right"   > <b>{{totnavarproc}}</b></td>
    {{/noshowpartion}}

      <td> </td>
    </tr>

</table>
{{/_type8}}     
{{#_type12}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_brendamy{{/t}}<br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">
     <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
   <th colspan="2" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

          <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>

    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
      {{^noshowpartion}}

  <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
  <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

     <th> </th>
    
</tr>
{{#_detail}}
<tr>
        <td align="right">{{docs}}</td>


    <td colspan="2">{{name}}</td>


    <td align="right">{{qty}}</td>
    <td align="right">{{summa}}</td>
    {{^noshowpartion}}

    <td align="right">{{navar}}</td>
        <td align="right" >{{navarproc}}</td>
    
    {{/noshowpartion}}
    <td> {{descr}}</td>
    
</tr>
{{/_detail}}
<tr><td colspan="4" ></td> <td align="right" ><b>{{totsumma}}</b></td> 
    {{^noshowpartion}}
<td align="right"   > <b>{{totnavar}}</b></td>
<td align="right"   > <b>{{totnavarproc}}</b></td>
    {{/noshowpartion}}
    <td> </td>
  </tr>
 
</table>
{{/_type12}}
{{#_type13}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.prodazhi_za_postachalnykamy{{/t}} <br> <br>
    </td>
</tr>
<tr style="font-weight: bolder;">

    <th colspan="4" style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>


    <th align="right" style="border: solid black 1px; ">{{#t}}common.na_sumu{{/t}}</th>
    
        {{^noshowpartion}}

    <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
    <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}

    <th></th>    
    <th></th>    
</tr>
{{#_detail}}
<tr>
   


    <td colspan="4">{{name}}</td>


    <td align="right">{{summa}}</td>
    {{^noshowpartion}}
    
    <td align="right">{{navar}}</td>
        <td align="right" >{{navarproc}}</td>
    {{/noshowpartion}}
    
    <td></td>  
    <td>{{descr}}</td>  
</tr>
{{/_detail}}
<tr><td colspan="4"></td><td align="right" ><b>{{totsumma}}</b></td>
    {{^noshowpartion}}

<td align="right" ><b>{{totnavar}}</b> </td> 
<td align="right" ><b>{{totnavarproc}}</b> </td> 

    {{/noshowpartion}}
     <td> </td>
</tr>

</table>
{{/_type13}}


{{#_type14}}
<tr style="font-size:larger; font-weight: bolder;">
    <td align="center" colspan="7">
        {{#t}}print.outcome.tovar_za_datamy{{/t}} <br> 
    </td>
</tr>
 
<tr style=" font-weight: bolder;">
    <td align="center" colspan="7">
        {{detname}} <br>  
    </td>
</tr>
 
<tr style="font-weight: bolder;">
     <th align="center"  style="border: solid black 1px">{{#t}}print.outcome.dok{{/t}}</th>
   <th style="border: solid black 1px;width:120px;">{{#t}}common.data{{/t}}</th>
 
    <th align="right" style="border: solid black 1px;width:100px;">{{#t}}common.kil{{/t}}</th>
    <th align="right" style="border: solid black 1px;width:100px;">{{#t}}common.na_sumu{{/t}}</th>
     {{^noshowpartion}}

  <th align="right" style="border: solid black 1px">{{#t}}common.prybutok{{/t}}</th>
  <th align="right" style="border: solid black 1px">{{#t}}print.outcome.pryb{{/t}}</th>
    {{/noshowpartion}}
    <th></th>

</tr>
{{#_detail}}
<tr>
         <td align="right">{{docs}}</td>


    <td>{{dt}}</td>

    <td align="right">{{qty}}</td>
    <td align="right">{{summa}}</td>
  
    {{^noshowpartion}}
  <td align="right">{{navar}}</td>
  
   <td align="right">{{navarproc}}</td>
  
  
 
    {{/noshowpartion}}
    <td> </td>

</tr>
{{/_detail}}
<tr>
<td colspan="3" align="right" ><b>{{totqty}}</b></td> 
<td   align="right" ><b>{{totsumma}}</b></td> 
    {{^noshowpartion}}

<td align="right" ><b>{{totnavar}}</b> </td> 
<td align="right" ><b>{{totnavarproc}}</b> </td> 

    {{/noshowpartion}}
<td   > </td> 
</tr>

</table>
{{/_type14}}