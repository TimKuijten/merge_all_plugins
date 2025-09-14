jQuery(function($){
    if ($('#wmh-map').length) {
        var tips = wmhData.countries || {};
        var colors = {};
        $.each(tips, function(code){
            colors[code] = '#0A212E';
        });
        $('#wmh-map').vectorMap({
            map: 'world_mill_en',
            backgroundColor: '#f5f5f5',
            regionStyle: {
                initial: {
                    fill: '#cccccc'
                }
            },
            series: {
                regions: [{
                    attribute: 'fill',
                    values: colors
                }]
            },
            onRegionTipShow: function(e, el, code){
                if (tips[code]) {
                    el.html(el.html() + ' - ' + tips[code]);
                }
            },
            onRegionClick: function(e, code){
                if (tips[code]) {
                    alert(tips[code]);
                }
            }
        });
    }
});
