jQuery(document).ready(function($) {
    'use strict';
    
    // Функція для ініціалізації полів вибору доставки
    function initDeliveryFields(ajaxUrl, translations) {
        $('#city').prop('disabled', true);
        $('#warehouses').prop('disabled', true);
        
        // Отримуємо регіони
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'delivery_get_areas',
            },
            success: function(response) {
                if (response.success) {
                    let areas = response.data;
                    $('#delivery').find('option').remove();
                    $('#delivery').append($("<option></option>", {
                        value: 0, 
                        text: translations.selectRegion
                    }));
                    
                    // Перевіряємо різні формати даних
                    let areasList = [];
                    
                    if (areas && typeof areas === 'object') {
                        // Перевіряємо дані в різних полях
                        if (areas.data && Array.isArray(areas.data)) {
                            areasList = areas.data;
                        } else if (areas.Data && Array.isArray(areas.Data)) {
                            areasList = areas.Data;
                        } else if (Array.isArray(areas)) {
                            areasList = areas;
                        }
                        
                        // Якщо знайдено масив регіонів
                        if (areasList.length > 0) {
                            for (let i = 0; i < areasList.length; i++) {
                                const area = areasList[i];
                                const id = area.Id || area.id || area.ID;
                                const name = area.Name || area.name || area.NAME || area.Description || area.description;
                                
                                if (id && name) {
                                    $('#delivery').append($("<option></option>", {
                                        value: id, 
                                        text: name
                                    }));
                                }
                            }
                        } else {
                            alert(translations.noRegions);
                        }
                    } else {
                        alert(translations.invalidData);
                    }
                } else {
                    alert(translations.errorLoadingRegions);
                }
            },
            error: function(xhr, status, error) {
                alert(translations.serverError);
            }
        });
    }
    
    // Обробник зміни регіону
    $(document).on('change', '#delivery', function() {
        if (this.value == 0) return;
        
        // Отримуємо міста за регіоном
        $.ajax({
            url: ipDelivery.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delivery_get_cities',
                area_id: this.value
            },
            success: function(response) {
                if (response.success) {
                    let cities = response.data;
                    $('#city').prop('disabled', false);
                    $('#city').find('option').remove();
                    $('input[name="delivery_delivery_name"]').val($('#delivery').find('option:selected').text());
                    $('#city').append($("<option></option>", {
                        value: 0, 
                        text: ipDelivery.translations.selectCity
                    }));
                    
                    if (Array.isArray(cities)) {
                        for (let i = 0; i < cities.length; i++) {
                            $('#city').append($("<option></option>", {
                                value: cities[i].id || cities[i].Id || cities[i].ID, 
                                text: cities[i].name || cities[i].Name || cities[i].NAME
                            }));
                        }
                    } else if (cities && cities.data && Array.isArray(cities.data)) {
                        for (let i = 0; i < cities.data.length; i++) {
                            $('#city').append($("<option></option>", {
                                value: cities.data[i].id || cities.data[i].Id || cities.data[i].ID, 
                                text: cities.data[i].name || cities.data[i].Name || cities.data[i].NAME
                            }));
                        }
                    }
                }
            }
        });
    });
    
    // Обробник зміни міста
    $(document).on('change', '#city', function() {
        if (this.value == 0) return;
        
        // Отримуємо відділення за містом
        $.ajax({
            url: ipDelivery.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delivery_get_warehouses',
                city_id: this.value
            },
            success: function(response) {
                if (response.success) {
                    let warehouses = response.data;
                    $('#warehouses').prop('disabled', false);
                    $('#warehouses').find('option').remove();
                    $('input[name="delivery_city_name"]').val($('#city').find('option:selected').text());
                    $('#warehouses').append($("<option></option>", {
                        value: 0, 
                        text: ipDelivery.translations.selectWarehouse
                    }));
                    
                    if (Array.isArray(warehouses)) {
                        for (let i = 0; i < warehouses.length; i++) {
                            let address = warehouses[i].address || warehouses[i].Address || warehouses[i].ADDRESS || '';
                            let name = warehouses[i].name || warehouses[i].Name || warehouses[i].NAME || '';
                            $('#warehouses').append($("<option></option>", {
                                value: warehouses[i].id || warehouses[i].Id || warehouses[i].ID, 
                                text: name + ' (' + address + ')'
                            }));
                        }
                    } else if (warehouses && warehouses.data && Array.isArray(warehouses.data)) {
                        for (let i = 0; i < warehouses.data.length; i++) {
                            let address = warehouses.data[i].address || warehouses.data[i].Address || warehouses.data[i].ADDRESS || '';
                            let name = warehouses.data[i].name || warehouses.data[i].Name || warehouses.data[i].NAME || '';
                            $('#warehouses').append($("<option></option>", {
                                value: warehouses.data[i].id || warehouses.data[i].Id || warehouses.data[i].ID, 
                                text: name + ' (' + address + ')'
                            }));
                        }
                    }
                }
            }
        });
    });
    
    // Обробник зміни відділення
    $(document).on('change', '#warehouses', function() {
        $('input[name="delivery_warehouses_name"]').val($(this).find('option:selected').text());
    });
    
    // Валідація перед відправкою форми
    $(document).on('checkout_place_order', function() {
        if($('input[name="shipping_method[0]"]:checked').val() === 'delivery') {
            var region = $('#delivery').val();
            var city = $('#city').val();
            var warehouse = $('#warehouses').val();
            
            if(region == '0' || region == undefined) {
                alert(ipDelivery.translations.pleaseSelectRegion);
                $('#delivery').focus();
                return false;
            }
            
            if(city == '0' || city == undefined) {
                alert(ipDelivery.translations.pleaseSelectCity);
                $('#city').focus();
                return false;
            }
            
            if(warehouse == '0' || warehouse == undefined) {
                alert(ipDelivery.translations.pleaseSelectWarehouse);
                $('#warehouses').focus();
                return false;
            }
        }
    });
    
    // Ініціалізація при завантаженні
    if(typeof ipDelivery !== 'undefined') {
        initDeliveryFields(
            ipDelivery.ajaxUrl,
            ipDelivery.translations
        );
    }
}); 