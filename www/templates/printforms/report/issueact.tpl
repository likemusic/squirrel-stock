<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="2">{{heading}}</td>
    </tr>
    <tr style="font-weight: bolder; vertical-align: top;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        {{#cols}}<th style="border: solid black 1px; min-width: 80px;">{{title}}</th>{{/cols}}
        <th style="border: solid black 1px;">{{#t}}report.issueact.total{{/t}}</th>
    </tr>
    <tr>
        <td style="border: solid black 1px;"></td>
        {{#cols}}<td style="border: solid black 1px; text-align: center;">{{n}}</td>{{/cols}}
        <td style="border: solid black 1px;"></td>
    </tr>
    {{#rows}}
    <tr>
        <td style="border: solid black 1px;">{{name}}</td>
        {{#cells}}<td style="border: solid black 1px; text-align: right;">{{qty}}</td>{{/cells}}
        <td style="border: solid black 1px; text-align: right; font-weight: bolder;">{{total}}</td>
    </tr>
    {{/rows}}
    <tr style="font-weight: bolder;">
        <td style="border: solid black 1px;">{{#t}}report.issueact.total{{/t}}</td>
        {{#totals}}<td style="border: solid black 1px; text-align: right;">{{qty}}</td>{{/totals}}
        <td style="border: solid black 1px; text-align: right;">{{total}}</td>
    </tr>
</table>
