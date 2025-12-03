(function () {
    function renderLineChart(config) {
        if (typeof d3 === 'undefined' || typeof wpPluginDashboardData === 'undefined') {
            return;
        }

        var container = document.getElementById(config.containerId);
        if (!container) {
            return;
        }

        var data = wpPluginDashboardData[config.dataKey] || [];
        if (!data.length) {
            container.innerHTML = '<p style="padding: 12px;">' + (config.emptyMessage || 'No analytics data available.') + '</p>';
            return;
        }

        var parseKey = d3.timeParse('%Y-%m');
        var series = data.map(function (d) {
            var parsed = {
                date: parseKey(d.key),
                label: d.label,
                year: d.year
            };
            config.series.forEach(function (s) {
                parsed[s.key] = +d[s.key];
            });
            return parsed;
        }).filter(function (d) { return d.date instanceof Date && !isNaN(d.date); });

        if (!series.length) {
            container.innerHTML = '<p style="padding: 12px;">Unable to parse analytics data.</p>';
            return;
        }

        container.innerHTML = '';

        var margin = { top: 20, right: 20, bottom: 30, left: 45 };
        var width = container.clientWidth ? container.clientWidth - margin.left - margin.right : 640 - margin.left - margin.right;
        var height = 240 - margin.top - margin.bottom;

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

        var x = d3.scaleTime()
            .domain(d3.extent(series, function (d) { return d.date; }))
            .range([0, width]);

        var yMax = d3.max(series, function (d) {
            return d3.max(config.series, function (s) { return d[s.key]; });
        }) || 1;

        var y = d3.scaleLinear()
            .domain([0, yMax])
            .nice()
            .range([height, 0]);

        // Axes
        svg.append('g')
            .attr('transform', 'translate(0,' + height + ')')
            .call(d3.axisBottom(x).ticks(6).tickFormat(d3.timeFormat('%b %y')))
            .selectAll('text')
            .style('font-size', '11px');

        svg.append('g')
            .call(d3.axisLeft(y).ticks(5))
            .selectAll('text')
            .style('font-size', '11px');

        var line = d3.line()
            .curve(d3.curveMonotoneX)
            .x(function (d) { return x(d.date); })
            .y(function (d) { return y(d.value); });

        config.series.forEach(function (serie) {
            svg.append('path')
                .datum(series.map(function (d) { return { date: d.date, value: d[serie.key] }; }))
                .attr('fill', 'none')
                .attr('stroke', serie.color)
                .attr('stroke-width', 2)
                .attr('d', line);
        });

        var tooltip = document.createElement('div');
        tooltip.className = 'wp-plugin-line-tooltip';
        container.appendChild(tooltip);

        function showTooltip(event, datum) {
            var rect = container.getBoundingClientRect();
            var inner = '<strong>' + datum.label + ' ' + datum.year + '</strong><br>';
            config.series.forEach(function (serie) {
                inner += serie.label + ': ' + datum[serie.key] + '<br>';
            });
            tooltip.innerHTML = inner;
            tooltip.style.left = (event.clientX - rect.left + 20) + 'px';
            tooltip.style.top = (event.clientY - rect.top - 10) + 'px';
            tooltip.style.opacity = 1;
        }

        function hideTooltip() {
            tooltip.style.opacity = 0;
        }

        config.series.forEach(function (serie) {
            svg.selectAll('.dot-' + serie.key)
                .data(series)
                .enter()
                .append('circle')
                .attr('class', 'dot-' + serie.key)
                .attr('cx', function (d) { return x(d.date); })
                .attr('cy', function (d) { return y(d[serie.key]); })
                .attr('r', 3.5)
                .attr('fill', serie.color)
                .attr('stroke', '#fff')
                .attr('stroke-width', 1)
                .style('cursor', 'pointer')
                .on('mouseenter', function (event, d) { showTooltip(event, d); })
                .on('mouseleave', hideTooltip);
        });

        var legend = document.createElement('div');
        legend.className = 'wp-plugin-line-legend';
        legend.innerHTML = config.series.map(function (serie) {
            return '<span><span class="dot" style="background: ' + serie.color + ';"></span>' + serie.label + '</span>';
        }).join('');
        container.appendChild(legend);
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderLineChart({
            containerId: 'wp-plugin-post-timeline',
            dataKey: 'postTimeline',
            emptyMessage: 'No post analytics data available.',
            series: [
                { key: 'total', label: 'Total Posts', color: '#1d6fa5' },
                { key: 'tagged', label: 'Tagged Posts', color: '#1fb141' }
            ]
        });

        renderTopTagsPie();

        renderLineChart({
    function renderTopTagsPie() {
        if (typeof d3 === 'undefined' || typeof wpPluginDashboardData === 'undefined') {
            return;
        }

        var container = document.getElementById('wp-plugin-top-tags-chart');
        if (!container) {
            return;
        }

        var data = (wpPluginDashboardData.topTags || []).slice(0, 8);

        if (!data.length) {
            container.innerHTML = '<p style="padding: 12px;">No tag data available yet.</p>';
            return;
        }

        container.innerHTML = '';

        var width = 320;
        var height = 260;
        var radius = Math.min(width, height) / 2;

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height)
            .append('g')
            .attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

        var color = d3.scaleOrdinal()
            .domain(data.map(function (d) { return d.name; }))
            .range(['#6c5ce7', '#00b894', '#0984e3', '#fdcb6e', '#e17055', '#fd79a8', '#636e72', '#2d3436']);

        var pie = d3.pie()
            .value(function (d) { return d.count; })
            .sort(null);

        var arc = d3.arc()
            .innerRadius(radius * 0.4)
            .outerRadius(radius - 10);

        var arcHover = d3.arc()
            .innerRadius(radius * 0.4)
            .outerRadius(radius);

        var tooltip = document.createElement('div');
        tooltip.className = 'wp-plugin-line-tooltip';
        container.appendChild(tooltip);

        function showTooltip(event, datum) {
            var rect = container.getBoundingClientRect();
            tooltip.innerHTML = '<strong>' + datum.data.name + '</strong><br>' + datum.data.count + ' posts';
            tooltip.style.left = (event.clientX - rect.left + 20) + 'px';
            tooltip.style.top = (event.clientY - rect.top - 10) + 'px';
            tooltip.style.opacity = 1;
        }

        function hideTooltip() {
            tooltip.style.opacity = 0;
        }

        var path = svg.selectAll('path')
            .data(pie(data))
            .enter()
            .append('path')
            .attr('d', arc)
            .attr('fill', function (d) { return color(d.data.name); })
            .attr('stroke', '#fff')
            .attr('stroke-width', 2)
            .style('cursor', 'pointer')
            .on('mouseenter', function (event, d) {
                d3.select(this).transition().duration(150).attr('d', arcHover);
                showTooltip(event, d);
            })
            .on('mouseleave', function () {
                d3.select(this).transition().duration(150).attr('d', arc);
                hideTooltip();
            });

        var legend = document.createElement('div');
        legend.className = 'wp-plugin-line-legend';
        legend.style.flexWrap = 'wrap';
        legend.innerHTML = data.map(function (datum) {
            return '<span><span class="dot" style="background:' + color(datum.name) + ';"></span>' + datum.name + '</span>';
        }).join('');
        container.appendChild(legend);
    }
            containerId: 'wp-plugin-tag-timeline',
            dataKey: 'tagTimeline',
            emptyMessage: 'No tag analytics data available.',
            series: [
                { key: 'assignments', label: 'Tag Assignments', color: '#7f4bc4' },
                { key: 'unique_tags', label: 'Unique Tags Applied', color: '#f29f05' }
            ]
        });
    });
})();