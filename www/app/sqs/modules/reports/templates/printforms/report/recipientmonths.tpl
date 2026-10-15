<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="3">{{#t}}report.recipientmonths.title{{/t}} {{period}}{{#filter}} ({{filter}}){{/filter}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}report.recipientmonths.recipient{{/t}}</th>
        {{#months}}<th style="border: solid black 1px; min-width: 60px;">{{m}}</th>{{/months}}
    </tr>
    {{#rows}}
    <tr>
        <td style="border: solid black 1px;">{{name}}</td>
        {{#cells}}<td style="border: solid black 1px; text-align: center;">{{v}}</td>{{/cells}}
    </tr>
    {{/rows}}
    <tr style="font-weight: bolder;">
        <td style="border: solid black 1px;">{{#t}}report.recipientmonths.recipients_in_month{{/t}}</td>
        {{#totals}}<td style="border: solid black 1px; text-align: center;">{{n}}</td>{{/totals}}
    </tr>
</table>
