(function ($) {
    showHideDependFields = function (fieldId, fieldName, fieldType) {
        var masterFieldsSelector = '.master-field input[type=\'checkbox\']:checked, .master-field input[type=\'radio\']:checked, .master-field select';
        $('#btn-submit').attr('disabled', 'disabled');
        $('#ajax-loading-animation').show();
        $.ajax({
            type: 'POST',
            url: siteUrl + 'index.php?option=com_osmembership&task=register.get_depend_fields_status&field_id=' + fieldId + langLinkForAjax,
            data: $(masterFieldsSelector),
            dataType: 'json',
            success: function (msg, textStatus, xhr) {
                $('#btn-submit').removeAttr('disabled');
                $('#ajax-loading-animation').hide();
                var hideFields = msg.hide_fields.split(',');
                var showFields = msg.show_fields.split(',');

                for (var i = 0; i < hideFields.length; i++) {
                    $('#' + hideFields[i]).hide();
                }

                for (var i = 0; i < showFields.length; i++) {
                    $('#' + showFields[i]).show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(textStatus);
            }
        });
    };

    populateSubscriberData = function () {
        var id = $('#user_id_id').val();
        var planId = $('#plan_id').val();
        $('#username_container').hide();
        $('#password_container').hide();
        $.ajax({
            type: 'GET',
            url: 'index.php?option=com_osmembership&task=get_profile_data&user_id=' + id + '&plan_id=' + planId,
            dataType: 'json',
            success: function (json) {
                var selecteds = [];
                for (var field in json) {
                    value = json[field];
                    if ($("input[name='" + field + "[]']").length) {
                        //This is a checkbox or multiple select
                        if ($.isArray(value)) {
                            selecteds = value;
                        }
                        else {
                            selecteds.push(value);
                        }
                        $("input[name='" + field + "[]']").val(selecteds);
                    }
                    else if ($("input[type='radio'][name='" + field + "']").length) {
                        $("input[name=" + field + "][value='" + value + "']").attr('checked', 'checked');
                    }
                    else {
                        $('#' + field).val(value);
                    }
                }
            }
        });
    };

    buildStateField = function (stateFieldId, countryFieldId, defaultState) {
        if ($('#' + stateFieldId).length && $('#' + stateFieldId).is('select')) {
            //set state
            if ($('#' + countryFieldId).length) {
                var countryName = $('#' + countryFieldId).val();
            }
            else {
                var countryName = '';
            }
            $.ajax({
                type: 'GET',
                url: siteUrl + 'index.php?option=com_osmembership&task=register.get_states&country_name=' + countryName + '&field_name=' + stateFieldId + '&state_name=' + defaultState + langLinkForAjax,
                success: function (data) {
                    if ($('#field_' + stateFieldId + ' .controls').length) {
                        $('#field_' + stateFieldId + ' .controls').html(data);
                    }
                    else {
                        $('#field_' + stateFieldId + ' .col-md-9').html(data);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    alert(textStatus);
                }
            });

            //Bind onchange event to the country
            if ($('#' + countryFieldId).length) {
                $('#' + countryFieldId).change(function () {
                    $.ajax({
                        type: 'GET',
                        url: siteUrl + 'index.php?option=com_osmembership&task=register.get_states&country_name=' + $(this).val() + '&field_name=' + stateFieldId + '&state_name=' + defaultState + langLinkForAjax,
                        success: function (data) {
                            if ($('#field_' + stateFieldId + ' .controls').length) {
                                $('#field_' + stateFieldId + ' .controls').html(data);
                            }
                            else {
                                $('#field_' + stateFieldId + ' .col-md-9').html(data);
                            }
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            alert(textStatus);
                        }
                    });
                });
            }
        }//end check exits state
    };
}(jQuery));
