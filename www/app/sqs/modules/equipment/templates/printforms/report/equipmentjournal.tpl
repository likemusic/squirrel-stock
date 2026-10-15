<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="12">{{#t}}report.equipmentjournal.title{{/t}} {{period}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px;">№</th>
        <th style="border: solid black 1px;">{{#t}}common.dohovir{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentjournal.contract_date{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.recipient{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentjournal.contact{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.sent_by{{/t}}</th>
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.inv{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentjournal.issue_date{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.term{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentjournal.return_date{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.prymitka{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td style="border: solid black 1px;">{{n}}</td>
        <td style="border: solid black 1px;" data-type="s">{{contract}}</td>
        <td style="border: solid black 1px;">{{contract_date}}</td>
        <td style="border: solid black 1px;">{{customer}}</td>
        <td style="border: solid black 1px;" data-type="s">{{contact}}</td>
        <td style="border: solid black 1px;">{{sent}}</td>
        <td style="border: solid black 1px;">{{itemname}}</td>
        <td style="border: solid black 1px;" data-type="s">{{snumber}}</td>
        <td style="border: solid black 1px;">{{date}}</td>
        <td style="border: solid black 1px;">{{term}}</td>
        <td style="border: solid black 1px;">{{returned}}</td>
        <td style="border: solid black 1px;">{{notes}}</td>
    </tr>
    {{/_detail}}
</table>
