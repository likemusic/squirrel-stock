<table class="ctable" border="0"  >

    <tr>
        <td align="center" colspan="4">
            <h4> {{#t}}print.prod.zvit_po_vyrobnytstvu{{/t}} </h4>
        </td>
    </tr>
    <tr>

        <td colspan="4">
            {{#t}}common.period_z{{/t}} <b>{{datefrom}}</b> {{#t}}common.po{{/t}} <b>{{dateto}}</b> <br>
        </td>
    </tr>
    {{#parea}}
    <tr>

        <td colspan="4">
            {{#t}}common.vyrobnycha_dilianka{{/t}} <b>{{parea}}</b><br>
        </td>
    </tr>
    {{/parea}}
    <tr>

        <td colspan="4">
            <h5> {{#t}}print.prod.spysano_na_vyrobnytstvo{{/t}} </h5>
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>


        <th align="right" style="border: solid black 1px">{{#t}}common.suma{{/t}}</th>
       </tr>
        {{#_detail}}
    <tr>


        <td>{{name}}</td>

        <td align="right">{{code}}</td>
        <td align="right">{{qty}}</td>


        <td align="right">{{summa}}</td>

    </tr>
    {{/_detail}}
    <tr>

        <td align="right" colspan="3">
            <b> {{#t}}common.na_sumu{{/t}} </b>
        </td>
        <td align="right"><b>{{sum1}}</b></td>
    </tr>

    <tr>

        <td colspan="4"><br>
            <h5> {{#t}}print.prod.oprybutkovano_z_vyrobnytstva{{/t}} </h5>
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>


        <th align="right" style="border: solid black 1px">{{#t}}common.suma{{/t}}</th>
      </tr>
        {{#_detail2}}
    <tr>


        <td>{{name}}</td>

        <td align="right">{{code}}</td>
        <td align="right">{{qty}}</td>


        <td align="right">{{summa}}</td>

    </tr>
    {{/_detail2}}
    <tr>

        <td align="right" colspan="3">
            <b> {{#t}}common.na_sumu{{/t}} </b>
        </td>
        <td align="right"><b>{{sum2}}</b></td>
    </tr>
    
   {{#ismove}}
      <tr>

        <td colspan="4">
            <h5> {{#t}}print.prod.peremishcheno_mizh_etapamy{{/t}} </h5>
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th   align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>
        <th   align="right" style="border: solid black 1px"> </th>

 
       </tr>
        {{#_detail4}}
     <tr>


        <td>{{name}}</td>

        <td align="right">{{code}}</td>
        <td   align="right">{{qty}}</td>
        <td   align="right"> </td>
  

    </tr>
    {{/_detail4}}
    {{/ismove}}    
    
    
    
    <tr>

        <td colspan="4"><br>
            <h5> {{#t}}print.prod.hotovo_do_vyrobnytstva{{/t}} </h5>
             {{#t}}print.prod.stanom_na{{/t}} <b> {{currdate}} </b>
             <br><small>{{#t}}print.prod.napivfabrykaty_ta_hotova_produktsiia_iak{{/t}} <br>{{#t}}print.prod.v_kilka_pozytsii_budut_pokazani{{/t}}
              </small>
        </td>
    </tr>

    <tr style="font-weight: bolder;">


        <th style="border: solid black 1px">{{#t}}common.naimenuvannia{{/t}}</th>

        <th style="border: solid black 1px">{{#t}}common.kod{{/t}}</th>
        <th colspan="2" align="right" style="border: solid black 1px">{{#t}}common.kil{{/t}}</th>

    </tr>


        {{#_detail3}}
    <tr>


        <td>{{name}}</td>

        <td align="right">{{code}}</td>
        <td colspan="2" align="right">{{qty}}</td>




    </tr>
    {{/_detail3}}

</table>


