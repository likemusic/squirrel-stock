<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="9">{{#t}}report.belowminimum.title{{/t}} {{#t}}report.belowminimum.on_date{{/t}} {{date}}{{#store}}, {{#t}}common.sklad{{/t}}: {{store}}{{/store}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.katehoriia{{/t}}</th>
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.kod{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.od_vym{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.belowminimum.qty{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.belowminimum.minqty{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.belowminimum.short{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.belowminimum.lastdate{{/t}}</th>
        <th style="border: solid black 1px; text-align: left;">{{#t}}report.belowminimum.lastfrom{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td style="border: solid black 1px;">{{cat}}</td>
        <td style="border: solid black 1px;">{{name}}</td>
        <td style="border: solid black 1px;" data-type="s">{{code}}</td>
        <td style="border: solid black 1px;">{{msr}}</td>
        <td style="border: solid black 1px; text-align: right;">{{qty}}</td>
        <td style="border: solid black 1px; text-align: right;">{{minqty}}</td>
        <td style="border: solid black 1px; text-align: right; font-weight: bolder;">{{short}}</td>
        <td style="border: solid black 1px;">{{lastdate}}</td>
        <td style="border: solid black 1px;">{{lastfrom}}</td>
    </tr>
    {{/_detail}}
    {{#empty}}
    <tr>
        <td colspan="9" style="border: solid black 1px;">{{#t}}report.belowminimum.none{{/t}}</td>
    </tr>
    {{/empty}}
</table>
