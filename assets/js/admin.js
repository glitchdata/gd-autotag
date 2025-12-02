(function () {
    function renderPostTimeline() {
        if (typeof d3 === 'undefined' || typeof wpPluginDashboardData === 'undefined') {
            return;
        }

        var container = document.getElementById('wp-plugin-post-timeline');
        if (!container) {
            return;
        }

        var data = wpPluginDashboardData.postTimeline || [];
        if (!data.length) {
            container.innerHTML = '<p style="padding: 12px;">No post analytics data available.</p>';
            return;
        }

        // Prepare data
        var parseKey = d3.timeParse('%Y-%m');
        var series = data.map(function (d) {
            return {
                date: parseKey(d.key),
                label: d.label,
                year: d.year,
                total: +d.total,
                tagged: +d.tagged
            };
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

        var y = d3.scaleLinear()
            .domain([0, d3.max(series, function (d) { return d.total; }) || 1])
            .nice()
            .range([height, 0]);

        var lineTotal = d3.line()
            .curve(d3.curveMonotoneX)
            .x(function (d) { return x(d.date); })
            .y(function (d) { return y(d.total); });

        var lineTagged = d3.line()
            .curve(d3.curveMonotoneX)
            .x(function (d) { return x(d.date); })
            .y(function (d) { return y(d.tagged); });

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

        // Lines
        svg.append('path')
            .datum(series)
            .attr('fill', 'none')
            .attr('stroke', '#1d6fa5')
            .attr('stroke-width', 2)
            .attr('d', lineTotal);

        svg.append('path')
            .datum(series)
            .attr('fill', 'none')
            .attr('stroke', '#1fb141')
            .attr('stroke-width', 2)
            .attr('d', lineTagged);

        var tooltip = document.createElement('div');
        tooltip.className = 'wp-plugin-line-tooltip';
        container.appendChild(tooltip);

        function showTooltip(event, datum) {
            var rect = container.getBoundingClientRect();
            tooltip.innerHTML = '<strong>' + datum.label + ' ' + datum.year + '</strong><br>' +
                'Total: ' + datum.total + '<br>' +
                'Tagged: ' + datum.tagged;
            tooltip.style.left = (event.clientX - rect.left + 20) + 'px';
            tooltip.style.top = (event.clientY - rect.top - 10) + 'px';
            tooltip.style.opacity = 1;
        }

        function hideTooltip() {
            tooltip.style.opacity = 0;
        }

        ['total', 'tagged'].forEach(function (seriesKey, idx) {
            svg.selectAll('.dot-' + seriesKey)
                .data(series)
                .enter()
                .append('circle')
                .attr('class', 'dot-' + seriesKey)
                .attr('cx', function (d) { return x(d.date); })
                .attr('cy', function (d) { return y(d[seriesKey]); })
                .attr('r', 3.5)
                .attr('fill', idx === 0 ? '#1d6fa5' : '#1fb141')
                .attr('stroke', '#fff')
                .attr('stroke-width', 1)
                .style('cursor', 'pointer')
                .on('mouseenter', function (event, d) { showTooltip(event, d); })
                .on('mouseleave', hideTooltip);
        });

        var legend = document.createElement('div');
        legend.className = 'wp-plugin-line-legend';
        legend.innerHTML = '<span><span class="dot total"></span>Total Posts</span>' +
            '<span><span class="dot tagged"></span>Tagged Posts</span>';
        container.appendChild(legend);
    }

    document.addEventListener('DOMContentLoaded', renderPostTimeline);
})();