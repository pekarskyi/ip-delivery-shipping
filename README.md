![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_main.png](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_main.png)

# Делівері для WooCommerce

Плагін інтеграції служби доставки "Делівері" з WooCommerce для інтернет-магазинів в Україні.

## Опис

Плагін додає метод доставки "Доставка Делівері" в WooCommerce, що дозволяє покупцям вибирати відділення компанії "Делівері" при оформленні замовлення. Плагін інтегрується з API "Делівері" для отримання актуальної інформації про доступні регіони, міста та відділення.

## Можливості

- Інтеграція з API служби доставки "Делівері"
- Підтримка зон доставки WooCommerce
- Додавання поля вибору області, міста та відділення при оформленні замовлення
- Автоматичне обчислення вартості доставки
- Кешування відповідей API для підвищення продуктивності
- Настроювана вартість доставки для різних зон
- Підтримка декількох методів доставки з різними тарифами
- Сумісність з HPOS (High-Performance Order Storage) WooCommerce
- Мови: англійська, українська.

## Скріншоти

Налаштування Делівері:
![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_sett_api.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_sett_api.jpg)

Додавання доставки Делівері в Зону доставки:
![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_zone.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_zone.jpg)

Делівері на сторінці оформлення:
![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form1.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form1.jpg)

Вибір регіону, міста та складу:
![https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form2.jpg](https://github.com/pekarskyi/assets/raw/master/ip-delivery-shipping/delivery_form2.jpg)

## Встановлення

1. Завантажте архів з плагіном.
2. Встановіть та Активуйте плагін через меню "Плагіни" в WordPress.
3. Налаштуйте плагін через меню WooCommerce -> Налаштування -> Доставка -> Доставка Делівері.
4. Додайте Зони доставки та налаштуйте Заголовок та Базову вартість.

## Налаштування API

1. Отримайте API ключі на сайті "Делівері" за посиланням: [https://www.delivery-auto.com/uk/Account/ApiKey](https://www.delivery-auto.com/uk/Account/ApiKey)
2. Вставте отримані ключі в налаштуваннях плагіну: WooCommerce -> Налаштування -> Доставка -> Доставка Делівері

## Налаштування зон доставки

1. Перейдіть в WooCommerce -> Налаштування -> Доставка
2. Виберіть потрібну зону або створіть нову
3. Додайте метод доставки "Доставка Делівері"
4. Налаштуйте параметри доставки для конкретної зони

## Кеш

Плагін кешує відповіді API для підвищення продуктивності. За замовчуванням термін дії кешу - 24 години.
Ви можете змінити тривалість кешування або очистити кеш в налаштуваннях.

## Фільтри та хуки

Плагін надає наступні фільтри для розробників:

### `ip_delivery_calculated_cost`

Цей фільтр дозволяє модифікувати розрахунок вартості доставки на основі різних параметрів.

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
add_filter('ip_delivery_calculated_cost', 'custom_delivery_cost_calculation', 10, 2);
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

**Приклад розрахунку вартості за містом:**

```php
// Розрахунок вартості на основі обраного міста
add_filter('ip_delivery_calculated_cost', 'city_based_delivery_cost', 10, 2);
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

**Безкоштовна доставка при замовленні від певної суми:**

```php
// Безкоштовна доставка при замовленні від 1000 грн
add_filter('ip_delivery_calculated_cost', 'free_delivery_for_expensive_orders', 10, 2);
function free_delivery_for_expensive_orders($cost, $data) {
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal >= 1000) {
        return 0; // Безкоштовна доставка
    }
    
    return $cost;
}
```

### `ip_delivery_cache_time`

Цей фільтр дозволяє змінити час кешування відповідей API (в секундах).

**Параметри:**
- `$cache_time` (int) - Час кешування в секундах (за замовчуванням 86400, тобто 24 години)

**Приклад використання:**

```php
// Змінюємо час кешування на 3 години
add_filter('ip_delivery_cache_time', 'modify_delivery_cache_time');
function modify_delivery_cache_time($time) {
    return 3 * 3600; // 3 години в секундах
}
```

## Програмне керування кешем

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
```

## Вимоги

- WordPress 6.7.2 або вище
- WooCommerce 9.6.0 або вище
- PHP 7.4 або вище
- При використанні HPOS в WooCommerce необхідно переконатися, що активовано режим сумісності плагінів

## Опис файлової структури:

Кореневі файли:

- `delivery.php` - головний файл плагіну
- `README.md` - документація

Директорії:

- `includes/` - містить класи плагіну
- `lang/` - переклади плагіну
- `updates/` - система оновлень через GitHub

Класи в директорії includes/:

- `class-plugin.php` - основний клас для управління плагіном
- `class-api.php` - взаємодія з API служби доставки
- `class-shipping-method.php` - реалізація методу доставки
- `class-checkout.php` - функціональність оформлення замовлення
- `class-ajax-handler.php` - обробка AJAX-запитів
- `class-admin.php` - адміністративний інтерфейс

## Список змін

1.1.0 - 29.03.2025:

- Підтримка Зон доставки
- Оновлення локалізації
- Реструктуризація коду
- Видалення даних плагіна з БД після видалення плагіна

1.0.0 - 28.03.2025:

- Інтеграція з API Делівері для отримання актуальних даних
- Вибір регіону, міста та відділення Делівері на сторінці оформлення замовлення
- Налаштування базової вартості доставки
- Можливість модифікації вартості доставки через фільтри WordPress
- Збереження даних доставки в метаданих замовлення
- Відображення обраного регіону, міста та відділення в деталях замовлення та email-повідомленнях
- Підтримка мультимовності через функції перекладу WordPress
- Автоматичне кешування даних API для покращення швидкодії
- Повна підтримка WooCommerce HPOS (High-Performance Order Storage)