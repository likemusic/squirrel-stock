// Текст выгрузки Excel (xlsx) для проверок: общие строки листа (xl/sharedStrings.xml) без разметки.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

async function xlsxText(request, url) {
  const resp = await request.get(url);
  const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'sqs-xlsx-')), 'report.xlsx');
  fs.writeFileSync(file, await resp.body());
  try {
    return execFileSync('unzip', ['-p', file, 'xl/sharedStrings.xml'], { encoding: 'utf8' }).replace(/<[^>]+>/g, ' ');
  } finally {
    fs.rmSync(path.dirname(file), { recursive: true, force: true });
  }
}

module.exports = { xlsxText };
