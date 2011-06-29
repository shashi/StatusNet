(function ($, SN) {
    $(document).ready(function () {
        var form = $('#newcheckin-form'),
            geoForm = function () {
            },

            getJSONgeocodeURL = function (geocodeURL, data) {
            },

            showMsg = function (msg) {
                form.find('.status').text(msg);
            };

            showMsg('Location not available...');
        if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        form.find('[name=lat]').val(position.coords.latitude);
                        form.find('[name=lon]').val(position.coords.longitude);

                        var data = {
                            lat: position.coords.latitude,
                            lon: position.coords.longitude,
                            token: $('#token').val()
                        };

                showMsg('Looking up place name...');
                var geocodeURL = form.find('.checkin_data-geo_wrap')
                                 .attr('data-api');

                $.getJSON(geocodeURL, data, function(location) {
                    var lns, lid;

                    if (typeof(location.location_ns) != 'undefined') {
                        form.find('[name=location_ns]').val(location.location_ns);
                        lns = location.location_ns;
                    }

                    if (typeof(location.location_id) != 'undefined') {
                        form.find('[name=location_id]').val(location.location_id);
                        lid = location.location_id;
                    }

                    if (typeof(location.name) == 'undefined') {
                        NLN_text = data.lat + ';' + data.lon;
                    }
                    else {
                        NLN_text = location.name;
                    }

                    SN.U.NoticeGeoStatus(form, NLN_text, data.lat, data.lon, location.url);

                    form.find('[name=lat]').val(data.lat);
                    form.find('[name=lon]').val(data.lon);
                    form.find('[name=location_ns]').val(lns);
                    form.find('[name=location_id]').val(lid);

                    var cookieValue = {
                        NLat: data.lat,
                        NLon: data.lon,
                        NLNS: lns,
                        NLID: lid,
                        NLN: NLN_text,
                        NLNU: location.url,
                        NDG: true
                    };

                    $.cookie(SN.C.S.NoticeDataGeoCookie, JSON.stringify(cookieValue), { path: '/' });
                });
                    },

                    function(error) {
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                showMessage('Location permission denied.');
                                break;
                            case error.TIMEOUT:
                                showMessage('Location lookup timeout.');
                                break;
                        }
                    },

                    {
                        timeout: 10000
                    }
                );
            }
        });
})(jQuery, SN);
