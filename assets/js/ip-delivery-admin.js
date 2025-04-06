/**
 * IP Delivery Admin JavaScript
 */

jQuery(document).ready(function($) {
    // Функція для конвертації чекбоксів у переключачі
    function convertCheckboxesToToggles() {
        // Перевіряємо, що ми на сторінці налаштувань доставки
        if (!$('body').hasClass('woocommerce_page_wc-settings') || 
            !$('input[name="section"]').val() === 'delivery') {
            return;
        }
        
        // Знаходимо всі чекбокси у формі налаштувань
        $('#mainform .form-table input[type="checkbox"]').each(function() {
            var $checkbox = $(this);
            var id = $checkbox.attr('id');
            
            // Пропускаємо, якщо вже оброблено
            if ($checkbox.parent().hasClass('delivery-toggle-switch')) {
                return;
            }
            
            // Створюємо структуру переключателя
            var $toggleSwitch = $('<label class="delivery-toggle-switch"></label>');
            var $toggleSlider = $('<span class="delivery-toggle-slider"></span>');
            
            // Обгортаємо чекбокс переключателем
            $checkbox.wrap($toggleSwitch);
            $checkbox.after($toggleSlider);
        });
    }
    
    // Конвертуємо чекбокси при завантаженні сторінки
    convertCheckboxesToToggles();
    
    // Запускаємо конвертацію також після AJAX запитів
    $(document).ajaxComplete(function() {
        convertCheckboxesToToggles();
    });
}); 