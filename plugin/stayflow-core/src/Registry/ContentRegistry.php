<?php

declare(strict_types=1);

namespace StayFlow\Registry; // Убран лишний \Core, теперь неймспейсы совпадают

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File: /stayflow-core/src/Registry/ContentRegistry.php
 * Version: 1.3.0
 * RU: Реестр контента (тексты/лейблы). Исправлен неймспейс и добавлены дефолтные тексты.
 * EN: Content registry (texts/labels). Fixed namespace and added default fallback texts.
 */
final class ContentRegistry extends AbstractRegistry
{
    protected function optionKey(): string
    {
        return 'stayflow_registry_content';
    }

    /**
     * RU: Переопределяем метод get, чтобы отдавать дефолтные тексты, если опция пуста.
     * EN: Override get method to provide default texts if option is empty.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->all();
        
        // Дефолтные тексты на случай, если в админке еще ничего не сохраняли
        $defaults = [
            'contract_party_text_a' => 'The contracting party is Stay4Fair.com. This property is managed by our professional partner.',
            'contract_party_text_b' => 'The contracting party for the accommodation is the respective property owner. Stay4Fair acts as an authorized intermediary.',
        ];

        if (empty($data) && array_key_exists($key, $defaults)) {
            return $defaults[$key];
        }

        return $data[$key] ?? $default;
    }
}