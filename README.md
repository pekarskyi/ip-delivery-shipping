![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_main.png](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_main.png)

# Делівері для WooCommerce

Плагін інтеграції служби доставки "Делівері" з WooCommerce для інтернет-магазинів в Україні.

[![GitHub release (latest by date)](https://img.shields.io/github/v/release/pekarskyi/ip-delivery-shipping?style=for-the-badge)](https://github.com/pekarskyi/ip-delivery-shipping/releases/)

## Опис

Плагін додає метод доставки "Доставка Делівері" в WooCommerce, що дозволяє покупцям вибирати відділення компанії "Делівері" при оформленні замовлення. Плагін інтегрується з API "Делівері" для отримання актуальної інформації про доступні регіони, міста та відділення.

### Основні можливості

- Інтеграція з API Делівері для отримання актуальних даних
- Вибір регіону, міста та відділення Делівері на сторінці оформлення замовлення
- Налаштування базової вартості доставки
- Можливість модифікації вартості доставки через фільтри WordPress
- Збереження даних доставки в метаданих замовлення
- Відображення обраного регіону, міста та відділення в деталях замовлення та email-повідомленнях
- Підтримка мультимовності через функції перекладу WordPress
- Автоматичне кешування даних API для покращення швидкодії
- Повна підтримка WooCommerce HPOS (High-Performance Order Storage)
- Мови: англійська, українська.
- Система оновлення плагіну з репозиторію

Увага! Дана версія плагіна (1.0.0) не підтримує різну базову вартість для різних Зон доставок! Тобто, одна базова вартість доставки діє для всіх зон.

### Підтримка WooCommerce HPOS

Плагін повністю сумісний з функцією WooCommerce HPOS (High-Performance Order Storage). Це означає, що:

- Плагін коректно працює як з традиційним зберіганням замовлень (у постах WordPress), так і з новим високопродуктивним сховищем (в окремих таблицях)
- Ви можете безпечно активувати HPOS у своєму магазині WooCommerce
- Всі метадані замовлень (регіон, місто, відділення доставки) коректно зберігаються і відображаються при будь-якому типі зберігання
- Плагін автоматично адаптується до вибраного методу зберігання замовлень

## Скріншоти

![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form1.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form1.jpg)

![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form2.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form2.jpg)

![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_sett.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_sett.jpg)

## Встановлення

### Варіант №1:

1. Завантажте плагін `Делівері для WooCommerce` (зелена кнопка Code - Download ZIP). Розпакуйте архів.
2. Завантажте папку з плагіном на ваш сайт WordPress в папку `/wp-content/plugins/`. Переконайтесь, що папка плагіна має назву `ip-delivery-shipping` (назва на роботу плагіна не впливає, але це впливає на отримання подальших оновлень).
3. Активуйте плагін.

### Варіант №2 (рекомендований):

1. Встановіть та активуйте даний плагін (інсталятор плагінів): https://github.com/pekarskyi/ip-installer
2. За допомогою плагіна `IP Installer` встановіть та активуйте плагін `Делівері для WooCommerce`.

## Налаштування

1. Перейдіть до розділу `IP Delivery`
2. Увімкніть метод доставки та налаштуйте його параметри:
   - Заголовок для відображення на сайті
   - Базова вартість доставки
   - API-ключі доступу до Делівері
   - Опція видалення даних при деінсталяції плагіна (видаляє всі налаштування та дані плагіна з бази даних при видаленні плагіна)

## Отримання API-ключів

Для роботи плагіну необхідно отримати API-ключі від Делівері. Для цього:

1. Зареєструйтесь або увійдіть в особистий кабінет на сайті [Delivery](https://www.delivery-auto.com/)
2. Перейдіть за посиланням [https://www.delivery-auto.com/uk/Account/ApiKey](https://www.delivery-auto.com/uk/Account/ApiKey)
3. Згенеруйте публічний та секретний ключі
4. Введіть отримані ключі в налаштуваннях плагіну

## Кешування даних API

Для підвищення швидкодії плагін автоматично кешує відповіді API Делівері:

- Кеш зберігається протягом 24 годин для найкращого балансу між актуальністю даних та швидкістю
- Кешуються списки областей, міст та відділень Делівері
- Адміністратор може очистити кеш вручну за потреби

## Для розробників

### Фільтри

Плагін надає наступні фільтри, які можна використовувати для кастомізації:

#### `woocommerce_delivery_calculate_shipping_cost`

Цей фільтр дозволяє модифікувати вартість доставки на основі різних параметрів.

**Параметри:**
- `$cost` (float) - Базова вартість доставки з налаштувань
- `$delivery_data` (array) - Масив даних для розрахунку вартості:
  - `package` - Дані пакету WooCommerce
  - `base_cost` - Базова вартість доставки
  - `instance_id` - ID екземпляра методу доставки
  - `region` - Обраний регіон
  - `city` - Обране місто
  - `warehouse` - Обране відділення

**Приклад використання:**

```php
// Розрахунок вартості на основі ваги товарів
add_filter('woocommerce_delivery_calculate_shipping_cost', 'custom_delivery_cost_calculation', 10, 2);
function custom_delivery_cost_calculation($cost, $data) {
    $package = $data['package'];
    $total_weight = 0;
    
    // Розрахунок загальної ваги
    foreach ($package['contents'] as $item) {
        $product = $item['data'];
        $weight = $product->get_weight() ? $product->get_weight() : 0;
        $total_weight += $weight * $item['quantity'];
    }
    
    // Збільшуємо вартість доставки на 5 грн за кожен кілограм
    if ($total_weight > 0) {
        $cost += $total_weight * 5;
    }
    
    return $cost;
}
```

#### Приклад розрахунку вартості за містом

```php
// Розрахунок вартості на основі обраного міста
add_filter('woocommerce_delivery_calculate_shipping_cost', 'city_based_delivery_cost', 10, 2);
function city_based_delivery_cost($cost, $data) {
    // Додаткова вартість для конкретних міст
    $city_costs = array(
        'Київ' => 100,
        'Львів' => 120,
        'Харків' => 110,
    );
    
    $city = $data['city'];
    if (isset($city_costs[$city])) {
        $cost = $city_costs[$city];
    }
    
    return $cost;
}
```

#### Розрахунок вартості на основі вартості кошика

```php
// Безкоштовна доставка при замовленні від 1000 грн
add_filter('woocommerce_delivery_calculate_shipping_cost', 'free_delivery_for_expensive_orders', 10, 2);
function free_delivery_for_expensive_orders($cost, $data) {
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal >= 1000) {
        return 0; // Безкоштовна доставка
    }
    
    return $cost;
}
```

### Керування кешуванням програмно

Розробники можуть програмно керувати кешуванням за допомогою стандартних функцій WordPress для роботи з transients:

```php
// Очистити весь кеш Delivery
function clear_all_delivery_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_delivery_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_delivery_%'");
}

// Очистити кеш для конкретного регіону
function clear_delivery_region_cache($area_id) {
    $cache_key = 'delivery_cities_uk-UA_' . $area_id;
    delete_transient($cache_key);
}

// Змінюємо час життя кешу
function custom_delivery_cache_time() {
    // Змінюємо час кешування на 3600 секунд (1 година)
    add_filter('delivery_cache_time', function() { return 3600; });
}
```

## Управління даними плагіна

Плагін зберігає свої дані в наступних місцях:

1. **Налаштування плагіна** - зберігаються в окремій таблиці бази даних `wp_ip_delivery_settings`.
2. **Дані про доставку для замовлень** - зберігаються в окремій таблиці бази даних `wp_ip_delivery_data`.
3. **Кешовані дані API** - зберігаються як transients в таблиці `wp_options` з префіксом `_transient_delivery_`.

При деінсталяції плагіна ви можете вибрати, чи видаляти всі дані:

- Увімкніть опцію **"Видалити дані при деінсталяції плагіна"** в налаштуваннях, щоб виконати повне видалення всіх даних з бази даних при видаленні плагіна.
- Якщо опція вимкнена, дані будуть збережені на випадок, якщо ви вирішите встановити плагін знову.

## Підтримка мультимовності

Плагін підтримує переклад через текстовий домен `ip-delivery-shipping`. Ви можете створити власні файли перекладу для будь-якої мови.

## Вимоги

- WordPress 6.7.2 або вище
- WooCommerce 9.6.0 або вище
- PHP 7.4 або вище
- При використанні HPOS в WooCommerce необхідно переконатися, що активовано режим сумісності плагінів

## Список змін

1.0.1 - 10.04.2025:
- Виправлення створення таблиць плагіна в БД

1.0.0 - 06.04.2025:
- Початковий випуск