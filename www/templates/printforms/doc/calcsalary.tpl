<table class="ctable" border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td  colspan="{{colspan}}">
            <b> {{#t}}print.calcsalary.narakhuvannia_zarplaty_vid{{/t}}</b> <br>
        </td>
    </tr>

   

    <tr>
        <td  colspan="{{colspan}}">
            <b>{{#t}}print.calcsalary.misiats{{/t}}</b>&nbsp;{{month}}&nbsp;{{year}}
        </td>
    </tr>

    {{#department}}  
   <tr>
        <td  colspan="{{colspan}}">
            <b>{{#t}}print.calcsalary.viddil{{/t}}</b>&nbsp;{{department}}  
        </td>
    </tr>
    {{/department}}  
    
    <tr>
        <td  colspan="{{colspan}}">
            {{{notes}}}
        </td>
    </tr>
    <tr>
        <td>
            <b>{{#t}}common.p_i_b{{/t}}</b>
        </td>
        {{#stnames}}
        <td align="text-right">
            <b>{{name}}</b>
        </td>
        {{/stnames}}
    </tr>

    {{#_detail}}
    <tr>
        <td>
            {{emp_name}}
        </td>
        {{#amounts}}
                <td  align="text-right">
            {{am}}
        </td>
        {{/amounts}}
    </tr>

    {{/_detail}}
    <tr>
        <td colspan="{{colspan}}" align="text-right">
            <b>{{#t}}print.calcsalary.vsoho_nbsp{{/t}}</b>
        </td>
         
    </tr>

</table>


