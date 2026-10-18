<table class="ctable{{#print}} d-print-none{{/print}}" border="0" cellpadding="2" cellspacing="0">
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
{{#parts}}
<!-- печать: куски по колонкам выдач, шапка повторяется на каждом листе -->
<table class="ctable d-none d-print-table" border="0" cellpadding="2" cellspacing="0" style="margin-bottom: 1em;">
    <thead>
    <tr style="font-weight: bolder;">
        <td colspan="{{span}}">{{heading}} {{part}}</td>
    </tr>
    <tr style="font-weight: bolder; vertical-align: top;">
        <th style="border: solid black 1px; text-align: left; min-width: 45mm;">{{#t}}common.naimenuvannia{{/t}}</th>
        {{#cols}}<th style="border: solid black 1px;">{{title}}</th>{{/cols}}
        {{#last}}<th style="border: solid black 1px;">{{#t}}report.issueact.total{{/t}}</th>{{/last}}
    </tr>
    <tr>
        <td style="border: solid black 1px;"></td>
        {{#cols}}<td style="border: solid black 1px; text-align: center;">{{n}}</td>{{/cols}}
        {{#last}}<td style="border: solid black 1px;"></td>{{/last}}
    </tr>
    </thead>
    <tbody>
    {{#rows}}
    <tr>
        <td style="border: solid black 1px;">{{name}}</td>
        {{#cells}}<td style="border: solid black 1px; text-align: right;">{{qty}}</td>{{/cells}}
        {{#last}}<td style="border: solid black 1px; text-align: right; font-weight: bolder;">{{total}}</td>{{/last}}
    </tr>
    {{/rows}}
    <tr style="font-weight: bolder;">
        <td style="border: solid black 1px;">{{#t}}report.issueact.total{{/t}}</td>
        {{#totals}}<td style="border: solid black 1px; text-align: right;">{{qty}}</td>{{/totals}}
        {{#last}}<td style="border: solid black 1px; text-align: right;">{{total}}</td>{{/last}}
    </tr>
    </tbody>
</table>
{{/parts}}
