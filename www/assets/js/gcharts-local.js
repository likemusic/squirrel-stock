/*
 * SquirrelStock: local replacement for the part of Google Charts used by the templates
 * (google.charts.load / setOnLoadCallback, google.visualization.arrayToDataTable, DataTable,
 * PieChart, ColumnChart, LineChart). Google Charts is loaded from the internet and cannot be
 * hosted locally, so without a network the dashboard failed with "google is not defined".
 * Drawing is done by Chart.js (assets/js/chart.umd.js, MIT), which must be loaded before this file.
 */
(function () {
  'use strict';

  var PALETTE = ['#3366cc', '#dc3912', '#ff9900', '#109618', '#990099', '#0099c6', '#dd4477', '#66aa00', '#b82e2e', '#316395'];
  var callbacks = [];
  var ready = false;

  function runCallbacks() {
    ready = true;
    while (callbacks.length) callbacks.shift()();
  }

  function DataTable(rows) {
    this.columns = [];
    this.rows = [];
    if (rows && rows.length) {
      var head = rows[0];
      for (var i = 0; i < head.length; i++) this.columns.push({ type: i === 0 ? 'string' : 'number', label: String(head[i]) });
      this.rows = rows.slice(1);
    }
  }
  DataTable.prototype.addColumn = function (type, label) { this.columns.push({ type: type, label: label }); };
  DataTable.prototype.addRows = function (rows) { this.rows = this.rows.concat(rows || []); };
  DataTable.prototype.addRow = function (row) { this.rows.push(row); };

  function color(o, path, def) {
    var v = o;
    for (var i = 0; i < path.length; i++) { if (v == null) return def; v = v[path[i]]; }
    return v == null ? def : v;
  }

  function Chart(type, el) {
    this.type = type;
    this.el = el;
    this.chart = null;
  }
  Chart.prototype.draw = function (data, options) {
    options = options || {};
    if (!this.el) return;
    if (this.chart) this.chart.destroy();
    var fg = color(options, ['titleTextStyle', 'color'], '#666');
    var bg = color(options, ['backgroundColor', 'fill'], 'transparent');
    this.el.innerHTML = '';
    this.el.style.position = 'relative';
    if (!this.el.style.height) this.el.style.height = (options.height || 250) + 'px';
    this.el.style.background = bg;
    var canvas = document.createElement('canvas');
    this.el.appendChild(canvas);

    var labels = data.rows.map(function (r) { return String(r[0]); });
    var datasets;
    if (this.type === 'pie') {
      // мелкие доли (меньше sliceVisibilityThreshold) не показываются, как в Google Charts
      var total = data.rows.reduce(function (s, r) { return s + (Number(r[1]) || 0); }, 0);
      var min = (options.sliceVisibilityThreshold || 0) * total;
      var rows = data.rows.filter(function (r) { return (Number(r[1]) || 0) >= min; });
      labels = rows.map(function (r) { return String(r[0]); });
      datasets = [{ data: rows.map(function (r) { return Number(r[1]) || 0; }), backgroundColor: PALETTE, borderColor: bg }];
    } else {
      datasets = data.columns.slice(1).map(function (c, i) {
        return {
          label: c.label,
          data: data.rows.map(function (r) { return Number(r[i + 1]) || 0; }),
          backgroundColor: PALETTE[i % PALETTE.length],
          borderColor: PALETTE[i % PALETTE.length],
          tension: options.curveType === 'function' ? 0.4 : 0,
        };
      });
    }
    var legendPos = color(options, ['legend', 'position'], this.type === 'pie' ? 'right' : 'right');
    var axisColor = color(options, ['hAxis', 'textStyle', 'color'], fg);
    var cfg = {
      type: this.type,
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          title: { display: !!options.title, text: options.title || '', color: fg, font: { weight: 'bold' } },
          legend: { display: legendPos !== 'none', position: legendPos, labels: { color: color(options, ['legendTextStyle', 'color'], fg) } },
        },
      },
    };
    if (this.type !== 'pie') {
      cfg.options.scales = {
        x: { ticks: { color: axisColor }, grid: { display: false } },
        y: { ticks: { color: color(options, ['vAxis', 'textStyle', 'color'], fg) }, beginAtZero: true },
      };
    }
    this.chart = new window.Chart(canvas, cfg);
  };

  function make(type) {
    return function (el) { return new Chart(type, el); };
  }

  window.google = window.google || {};
  window.google.charts = {
    load: function () {
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', runCallbacks);
      else setTimeout(runCallbacks, 0);
    },
    setOnLoadCallback: function (fn) {
      if (ready) fn();
      else callbacks.push(fn);
    },
  };
  window.google.visualization = {
    DataTable: DataTable,
    arrayToDataTable: function (rows) { return new DataTable(rows); },
    PieChart: make('pie'),
    ColumnChart: make('bar'),
    LineChart: make('line'),
  };
})();
